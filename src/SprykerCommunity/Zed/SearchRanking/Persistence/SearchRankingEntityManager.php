<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use DateTime;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer;
use Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingScopeCopyLock;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingSettingHistory;
use Propel\Runtime\Exception\PropelException;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use SprykerCommunity\Zed\SearchRanking\Persistence\Exception\ConcurrentScopeCopyLockException;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingPersistenceFactory getFactory()
 */
class SearchRankingEntityManager extends AbstractEntityManager implements SearchRankingEntityManagerInterface
{
    use TransactionTrait;

    /**
     * MySQL's own duplicate-entry error code -- used to distinguish "the active_target_scope_key unique
     * index rejected this insert" from any other PropelException, since Propel does not expose a typed
     * exception for constraint violations specifically. Same mechanism search-index-alias's own
     * SearchIndexAliasEntityManager uses for its active_scope_key column.
     *
     * @var string
     */
    protected const MYSQL_DUPLICATE_ENTRY_SQLSTATE = '23000';

    /**
     * Writes the metric's global identity (name/isHigherBetter) here, then its formula/isActive/shape
     * separately to `spy_search_ranking_metric_store_config` for `$storeName` via
     * {@see saveMetricStoreConfig()} — a brand-new metric's identity row is created first so a real id
     * exists for the store-config row's foreign key.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): SearchRankingMetricTransfer
    {
        $metricEntity = null;

        if ($metricTransfer->getIdSearchRankingMetric() !== null) {
            $metricEntity = $this->getFactory()
                ->createSearchRankingMetricQuery()
                ->findOneByIdSearchRankingMetric($metricTransfer->getIdSearchRankingMetric());
        }

        $metricEntity ??= new SpySearchRankingMetric();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $metricEntity = $mapper->mapMetricTransferToEntity($metricTransfer, $metricEntity);
        $metricEntity->save();

        $savedMetricTransfer = $mapper->mapMetricEntityToTransfer($metricEntity, $metricTransfer);

        $this->saveMetricStoreConfig(
            (new SearchRankingMetricStoreConfigTransfer())
                ->setFkSearchRankingMetric($savedMetricTransfer->getIdSearchRankingMetricOrFail())
                ->setStoreName($storeName)
                ->setLocaleName($localeName)
                ->setFormula($metricTransfer->getFormulaOrFail())
                ->setIsActive($metricTransfer->getIsActive() ?? true)
                ->setShape($metricTransfer->getShape()),
        );

        // weight isn't a column on SpySearchRankingMetric — carry the incoming transfer's own weight
        // through unchanged, since mapMetricEntityToTransfer() never touches it. formula/isActive/shape
        // are similarly untouched by that call now (see its own docblock) — $savedMetricTransfer is the
        // SAME object as $metricTransfer, so they already carry exactly what was just persisted above.
        return $savedMetricTransfer->setWeight($metricTransfer->getWeight());
    }

    /**
     * @param int $idSearchRankingMetric
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $metricEntity = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->findOneByIdSearchRankingMetric($idSearchRankingMetric);

        if ($metricEntity === null) {
            return;
        }

        $metricEntity->delete();
    }

    /**
     * @param array<int, float> $normalizedValuesByIdProductMetric
     */
    public function updateNormalizedValues(array $normalizedValuesByIdProductMetric): void
    {
        if ($normalizedValuesByIdProductMetric === []) {
            return;
        }

        $productMetricEntities = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByIdSearchRankingProductMetric_In(array_keys($normalizedValuesByIdProductMetric))
            ->find();

        // A batch can run to thousands of rows; wrapped in one transaction so a mid-batch DB error or
        // connection drop can't leave earlier rows in the batch committed and later ones unwritten --
        // same convention search-ranking-optimizer's entity manager uses for its own multi-row writes.
        $this->getTransactionHandler()->handleTransaction(function () use ($productMetricEntities, $normalizedValuesByIdProductMetric): void {
            foreach ($productMetricEntities as $productMetricEntity) {
                $productMetricEntity->setNormalizedValue(
                    $normalizedValuesByIdProductMetric[$productMetricEntity->getIdSearchRankingProductMetric()],
                );
                $productMetricEntity->save();
            }
        });
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     */
    public function saveMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName, float $weight): void
    {
        $metricWeightEntity = $this->getFactory()
            ->createSearchRankingMetricWeightQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->findOneOrCreate();

        $metricWeightEntity->setWeight($weight);
        $metricWeightEntity->save();
    }

    /**
     * @param array<int, float> $weightsByIdSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function updateMetricWeights(array $weightsByIdSearchRankingMetric, string $storeName, string $localeName): void
    {
        foreach ($weightsByIdSearchRankingMetric as $idSearchRankingMetric => $weight) {
            $this->saveMetricWeight($idSearchRankingMetric, $storeName, $localeName, $weight);
        }
    }

    /**
     * @param string $settingKey
     * @param string $storeName
     * @param string $localeName
     * @param string $settingValue
     */
    public function saveSetting(string $settingKey, string $storeName, string $localeName, string $settingValue): void
    {
        $settingEntity = $this->getFactory()
            ->createSearchRankingSettingQuery()
            ->filterBySettingKey($settingKey)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->findOneOrCreate();

        $settingEntity->setSettingValue($settingValue);
        $settingEntity->save();
    }

    /**
     * Always inserts a new row — history is append-only, never updated or upserted, unlike every other
     * write in this class.
     *
     * @param \Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer $settingHistoryTransfer
     */
    public function recordSettingHistory(SearchRankingSettingHistoryTransfer $settingHistoryTransfer): SearchRankingSettingHistoryTransfer
    {
        $settingHistoryEntity = new SpySearchRankingSettingHistory();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $settingHistoryEntity = $mapper->mapSettingHistoryTransferToEntity($settingHistoryTransfer, $settingHistoryEntity);
        $settingHistoryEntity->save();

        return $mapper->mapSettingHistoryEntityToTransfer($settingHistoryEntity, $settingHistoryTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     */
    public function saveMetricDigest(SearchRankingMetricDigestTransfer $digestTransfer): SearchRankingMetricDigestTransfer
    {
        $digestEntity = $this->getFactory()
            ->createSearchRankingMetricDigestQuery()
            ->filterByFkSearchRankingMetric($digestTransfer->getFkSearchRankingMetricOrFail())
            ->filterByStoreName($digestTransfer->getStoreNameOrFail())
            ->filterByLocaleName($digestTransfer->getLocaleNameOrFail())
            ->findOneOrCreate();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $digestEntity = $mapper->mapMetricDigestTransferToEntity($digestTransfer, $digestEntity);
        $digestEntity->save();

        return $mapper->mapMetricDigestEntityToTransfer($digestEntity, $digestTransfer);
    }

    /**
     * Always inserts a new row — history is append-only, never updated or upserted, unlike every other
     * write in this class.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer $historyTransfer
     */
    public function recordMetricHistory(SearchRankingMetricHistoryTransfer $historyTransfer): SearchRankingMetricHistoryTransfer
    {
        $historyEntity = new SpySearchRankingMetricHistory();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $historyEntity = $mapper->mapMetricHistoryTransferToEntity($historyTransfer, $historyEntity);
        $historyEntity->save();

        return $mapper->mapMetricHistoryEntityToTransfer($historyEntity, $historyTransfer);
    }

    /**
     * Always inserts a new row — history is append-only, never updated or upserted, unlike every other
     * write in this class.
     *
     * `active_target_scope_key`'s own UNIQUE index is the real backstop against two concurrent calls
     * targeting the same scope — ScopeCopyLockValidator's own read-then-write check cannot close that race
     * on its own (see that class's docblock). A rejected insert here means a concurrent request won it.
     *
     * @param \Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer $scopeCopyLockTransfer
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Persistence\Exception\ConcurrentScopeCopyLockException
     */
    public function createScopeCopyLock(SearchRankingScopeCopyLockTransfer $scopeCopyLockTransfer): SearchRankingScopeCopyLockTransfer
    {
        $scopeCopyLockEntity = new SpySearchRankingScopeCopyLock();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $scopeCopyLockEntity = $mapper->mapScopeCopyLockTransferToEntity($scopeCopyLockTransfer, $scopeCopyLockEntity);
        $this->applyActiveTargetScopeKey($scopeCopyLockEntity);

        try {
            $scopeCopyLockEntity->save();
        } catch (PropelException $propelException) {
            if ($this->isDuplicateActiveTargetScopeKey($propelException)) {
                throw new ConcurrentScopeCopyLockException(sprintf(
                    '%s/%s is already the target of an active lock.',
                    $scopeCopyLockTransfer->getTargetStoreNameOrFail(),
                    $scopeCopyLockTransfer->getTargetLocaleNameOrFail(),
                ), 0, $propelException);
            }

            throw $propelException;
        }

        return $mapper->mapScopeCopyLockEntityToTransfer($scopeCopyLockEntity, $scopeCopyLockTransfer);
    }

    /**
     * @param int $idSearchRankingScopeCopyLock
     */
    public function deactivateScopeCopyLock(int $idSearchRankingScopeCopyLock): void
    {
        $scopeCopyLockEntity = $this->getFactory()
            ->createSearchRankingScopeCopyLockQuery()
            ->findOneByIdSearchRankingScopeCopyLock($idSearchRankingScopeCopyLock);

        if ($scopeCopyLockEntity === null || !$scopeCopyLockEntity->getIsActive()) {
            return;
        }

        $scopeCopyLockEntity->setIsActive(false);
        $scopeCopyLockEntity->setDeactivatedAt(new DateTime());
        $this->applyActiveTargetScopeKey($scopeCopyLockEntity);
        $scopeCopyLockEntity->save();
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingScopeCopyLock $scopeCopyLockEntity
     */
    protected function applyActiveTargetScopeKey(SpySearchRankingScopeCopyLock $scopeCopyLockEntity): void
    {
        if (!$scopeCopyLockEntity->getIsActive()) {
            $scopeCopyLockEntity->setActiveTargetScopeKey(null);

            return;
        }

        $scopeCopyLockEntity->setActiveTargetScopeKey(sprintf(
            '%s:%s',
            $scopeCopyLockEntity->getTargetStoreName(),
            $scopeCopyLockEntity->getTargetLocaleName(),
        ));
    }

    /**
     * @param \Propel\Runtime\Exception\PropelException $propelException
     */
    protected function isDuplicateActiveTargetScopeKey(PropelException $propelException): bool
    {
        $previous = $propelException->getPrevious();

        if ($previous === null) {
            return false;
        }

        return $previous->getCode() === static::MYSQL_DUPLICATE_ENTRY_SQLSTATE
            && str_contains($previous->getMessage(), 'active_target_scope_key');
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer $metricStoreConfigTransfer
     */
    public function saveMetricStoreConfig(SearchRankingMetricStoreConfigTransfer $metricStoreConfigTransfer): SearchRankingMetricStoreConfigTransfer
    {
        $metricStoreConfigEntity = $this->getFactory()
            ->createSearchRankingMetricStoreConfigQuery()
            ->filterByFkSearchRankingMetric($metricStoreConfigTransfer->getFkSearchRankingMetricOrFail())
            ->filterByStoreName($metricStoreConfigTransfer->getStoreNameOrFail())
            ->filterByLocaleName($metricStoreConfigTransfer->getLocaleNameOrFail())
            ->findOneOrCreate();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $metricStoreConfigEntity = $mapper->mapMetricStoreConfigTransferToEntity($metricStoreConfigTransfer, $metricStoreConfigEntity);
        $metricStoreConfigEntity->save();

        return $mapper->mapMetricStoreConfigEntityToTransfer($metricStoreConfigEntity, $metricStoreConfigTransfer);
    }

    /**
     * @param int $idProductAbstract
     * @param string $storeName
     * @param string $localeName
     * @param string $modelId
     * @param array<int, float> $vector
     * @param string $textHash
     */
    public function upsertEmbedding(
        int $idProductAbstract,
        string $storeName,
        string $localeName,
        string $modelId,
        array $vector,
        string $textHash,
    ): void {
        $embeddingEntity = $this->getFactory()
            ->createSearchRankingEmbeddingQuery()
            ->filterByFkProductAbstract($idProductAbstract)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->filterByModelId($modelId)
            ->findOneOrCreate();

        $embeddingEntity->setFkProductAbstract($idProductAbstract);
        $embeddingEntity->setStoreName($storeName);
        $embeddingEntity->setLocaleName($localeName);
        $embeddingEntity->setModelId($modelId);
        $embeddingEntity->setVector(json_encode($vector, JSON_THROW_ON_ERROR));
        $embeddingEntity->setTextHash($textHash);
        $embeddingEntity->save();
    }
}
