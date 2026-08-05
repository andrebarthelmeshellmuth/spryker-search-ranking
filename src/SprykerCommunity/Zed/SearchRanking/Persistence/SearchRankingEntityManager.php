<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingSettingHistory;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingPersistenceFactory getFactory()
 */
class SearchRankingEntityManager extends AbstractEntityManager implements SearchRankingEntityManagerInterface
{
    use TransactionTrait;

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer
    {
        $metricEntity = null;

        if ($metricTransfer->getIdSearchRankingMetric() !== null) {
            $metricEntity = $this->getFactory()
                ->createSearchRankingMetricQuery()
                ->findOneByIdSearchRankingMetric($metricTransfer->getIdSearchRankingMetric());
        }

        if ($metricEntity === null) {
            $metricEntity = new SpySearchRankingMetric();
        }

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $metricEntity = $mapper->mapMetricTransferToEntity($metricTransfer, $metricEntity);
        $metricEntity->save();

        $savedMetricTransfer = $mapper->mapMetricEntityToTransfer($metricEntity, $metricTransfer);

        // weight isn't a column on SpySearchRankingMetric — carry the incoming transfer's own weight
        // through unchanged, since mapMetricEntityToTransfer() never touches it.
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
}
