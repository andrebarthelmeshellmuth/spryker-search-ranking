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
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingPersistenceFactory getFactory()
 */
class SearchRankingEntityManager extends AbstractEntityManager implements SearchRankingEntityManagerInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
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

        // mapMetricEntityToTransfer() no longer sets weight (it's not an entity column anymore) — carry
        // the incoming transfer's own weight through unchanged, since this method never touches it.
        return $savedMetricTransfer->setWeight($metricTransfer->getWeight());
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return void
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
     *
     * @return void
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

        foreach ($productMetricEntities as $productMetricEntity) {
            $productMetricEntity->setNormalizedValue(
                $normalizedValuesByIdProductMetric[$productMetricEntity->getIdSearchRankingProductMetric()],
            );
            $productMetricEntity->save();
        }
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
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
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer
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
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer
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
