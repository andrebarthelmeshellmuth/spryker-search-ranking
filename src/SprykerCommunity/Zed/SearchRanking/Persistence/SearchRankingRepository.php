<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingMetricTableMap;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingProductMetricTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingPersistenceFactory getFactory()
 */
class SearchRankingRepository extends AbstractRepository implements SearchRankingRepositoryInterface
{
    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        $metricEntities = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->orderByName()
            ->find();

        $collectionTransfer = new SearchRankingMetricCollectionTransfer();
        $mapper = $this->getFactory()->createSearchRankingMapper();

        foreach ($metricEntities as $metricEntity) {
            $metricTransfer = $mapper->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());
            $collectionTransfer->addMetric($this->attachWeight($metricTransfer, $storeName, $localeName));
        }

        return $collectionTransfer;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        $metricEntities = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->filterByIsActive(true)
            ->orderByName()
            ->find();

        $collectionTransfer = new SearchRankingMetricCollectionTransfer();
        $mapper = $this->getFactory()->createSearchRankingMapper();

        foreach ($metricEntities as $metricEntity) {
            $metricTransfer = $mapper->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());
            $collectionTransfer->addMetric($this->attachWeight($metricTransfer, $storeName, $localeName));
        }

        return $collectionTransfer;
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricTransfer
    {
        $metricEntity = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->findOneByIdSearchRankingMetric($idSearchRankingMetric);

        if ($metricEntity === null) {
            return null;
        }

        $metricTransfer = $this->getFactory()
            ->createSearchRankingMapper()
            ->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());

        return $this->attachWeight($metricTransfer, $storeName, $localeName);
    }

    /**
     * @param string $name
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricByName(string $name, string $storeName, string $localeName): ?SearchRankingMetricTransfer
    {
        $metricEntity = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->findOneByName($name);

        if ($metricEntity === null) {
            return null;
        }

        $metricTransfer = $this->getFactory()
            ->createSearchRankingMapper()
            ->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());

        return $this->attachWeight($metricTransfer, $storeName, $localeName);
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    protected function attachWeight(
        SearchRankingMetricTransfer $metricTransfer,
        string $storeName,
        string $localeName,
    ): SearchRankingMetricTransfer {
        $weight = $this->findMetricWeight($metricTransfer->getIdSearchRankingMetricOrFail(), $storeName, $localeName);

        return $metricTransfer->setWeight($weight ?? 0.0);
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return float|null
     */
    public function findMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName): ?float
    {
        $metricWeightEntity = $this->getFactory()
            ->createSearchRankingMetricWeightQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->findOne();

        return $metricWeightEntity?->getWeight();
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer
     */
    public function getMetricStatistics(int $idSearchRankingMetric, string $storeName, string $localeName): SearchRankingMetricStatisticsTransfer
    {
        /** @var array<string, mixed>|null $statisticsRow */
        $statisticsRow = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->withColumn(sprintf('MIN(%s)', SpySearchRankingProductMetricTableMap::COL_RAW_VALUE), 'min_value')
            ->withColumn(sprintf('MAX(%s)', SpySearchRankingProductMetricTableMap::COL_RAW_VALUE), 'max_value')
            ->withColumn(sprintf('AVG(%s)', SpySearchRankingProductMetricTableMap::COL_RAW_VALUE), 'avg_value')
            ->withColumn('COUNT(*)', 'row_count')
            ->select(['min_value', 'max_value', 'avg_value', 'row_count'])
            ->findOne();

        return (new SearchRankingMetricStatisticsTransfer())
            ->setMinValue((float)($statisticsRow['min_value'] ?? 0.0))
            ->setMaxValue((float)($statisticsRow['max_value'] ?? 0.0))
            ->setAvgValue((float)($statisticsRow['avg_value'] ?? 0.0))
            ->setCount((int)($statisticsRow['row_count'] ?? 0));
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param int $idLastSearchRankingProductMetric
     * @param int $limit
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingProductMetricTransfer>
     */
    public function getProductMetricBatch(
        int $idSearchRankingMetric,
        string $storeName,
        string $localeName,
        int $idLastSearchRankingProductMetric,
        int $limit,
    ): array {
        $productMetricEntities = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->filterByIdSearchRankingProductMetric($idLastSearchRankingProductMetric, Criteria::GREATER_THAN)
            ->orderByIdSearchRankingProductMetric()
            ->limit($limit)
            ->find();

        $productMetricTransfers = [];
        $mapper = $this->getFactory()->createSearchRankingMapper();

        foreach ($productMetricEntities as $productMetricEntity) {
            $productMetricTransfers[] = $mapper->mapProductMetricEntityToTransfer(
                $productMetricEntity,
                new SearchRankingProductMetricTransfer(),
            );
        }

        return $productMetricTransfers;
    }

    /**
     * @param array<int> $productAbstractIds
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<int, array<string, float>>
     */
    public function getNormalizedScoresGroupedByIdProductAbstract(array $productAbstractIds, string $storeName, string $localeName): array
    {
        if ($productAbstractIds === []) {
            return [];
        }

        $scoreRows = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByFkProductAbstract_In($productAbstractIds)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->filterByNormalizedValue(null, Criteria::ISNOTNULL)
            ->useSearchRankingMetricQuery()
                ->filterByIsActive(true)
            ->endUse()
            ->withColumn(SpySearchRankingMetricTableMap::COL_NAME, 'metric_name')
            ->select([
                SpySearchRankingProductMetricTableMap::COL_FK_PRODUCT_ABSTRACT,
                SpySearchRankingProductMetricTableMap::COL_NORMALIZED_VALUE,
                'metric_name',
            ])
            ->find();

        $scoresByIdProductAbstract = [];

        foreach ($scoreRows as $scoreRow) {
            $idProductAbstract = (int)$scoreRow[SpySearchRankingProductMetricTableMap::COL_FK_PRODUCT_ABSTRACT];
            $metricName = (string)$scoreRow['metric_name'];
            $scoresByIdProductAbstract[$idProductAbstract][$metricName] = (float)$scoreRow[SpySearchRankingProductMetricTableMap::COL_NORMALIZED_VALUE];
        }

        return $scoresByIdProductAbstract;
    }

    /**
     * @return array<int>
     */
    public function getProductAbstractIdsWithActiveMetricValues(): array
    {
        /** @var array<int|string> $productAbstractIds */
        $productAbstractIds = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByNormalizedValue(null, Criteria::ISNOTNULL)
            ->useSearchRankingMetricQuery()
                ->filterByIsActive(true)
            ->endUse()
            ->select([SpySearchRankingProductMetricTableMap::COL_FK_PRODUCT_ABSTRACT])
            ->distinct()
            ->find()
            ->getData();

        return array_map(static fn ($value): int => (int)$value, $productAbstractIds);
    }

    /**
     * @param string $settingKey
     * @param string $storeName
     * @param string $localeName
     *
     * @return string|null
     */
    public function findSettingValue(string $settingKey, string $storeName, string $localeName): ?string
    {
        $settingEntity = $this->getFactory()
            ->createSearchRankingSettingQuery()
            ->filterBySettingKey($settingKey)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->findOne();

        return $settingEntity?->getSettingValue();
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<float>
     */
    public function getRawValues(int $idSearchRankingMetric, string $storeName, string $localeName): array
    {
        /** @var array<int|string|float> $rawValues */
        $rawValues = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->select([SpySearchRankingProductMetricTableMap::COL_RAW_VALUE])
            ->find()
            ->getData();

        return array_map(static fn ($value): float => (float)$value, $rawValues);
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer|null
     */
    public function findMetricDigest(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricDigestTransfer
    {
        $digestEntity = $this->getFactory()
            ->createSearchRankingMetricDigestQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->findOne();

        if ($digestEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createSearchRankingMapper()
            ->mapMetricDigestEntityToTransfer($digestEntity, new SearchRankingMetricDigestTransfer());
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer>
     */
    public function getMetricHistory(int $idSearchRankingMetric, string $storeName, string $localeName): array
    {
        $historyEntities = $this->getFactory()
            ->createSearchRankingMetricHistoryQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->orderByIdSearchRankingMetricHistory(Criteria::DESC)
            ->find();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $historyTransfers = [];

        foreach ($historyEntities as $historyEntity) {
            $historyTransfers[] = $mapper->mapMetricHistoryEntityToTransfer($historyEntity, new SearchRankingMetricHistoryTransfer());
        }

        return $historyTransfers;
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer|null
     */
    public function findLastMetricChangeHistoryEntry(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricHistoryTransfer
    {
        $historyEntity = $this->getFactory()
            ->createSearchRankingMetricHistoryQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->filterByIsChange(true)
            ->orderByIdSearchRankingMetricHistory(Criteria::DESC)
            ->findOne();

        if ($historyEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createSearchRankingMapper()
            ->mapMetricHistoryEntityToTransfer($historyEntity, new SearchRankingMetricHistoryTransfer());
    }
}
