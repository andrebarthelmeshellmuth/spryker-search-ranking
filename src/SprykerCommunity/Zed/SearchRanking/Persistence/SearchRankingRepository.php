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
use Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingMetricStoreConfigTableMap;
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
            $metricTransfer = $this->attachStoreConfig($metricTransfer, $storeName);
            $collectionTransfer->addMetric($this->attachWeight($metricTransfer, $storeName, $localeName));
        }

        return $collectionTransfer;
    }

    /**
     * "Active" is a per-store fact (`spy_search_ranking_metric_store_config.is_active`), so this can't
     * filter at the SQL level on `spy_search_ranking_metric` itself; it queries every metric, overlays
     * this store's real isActive via {@see attachStoreConfig()}, then filters in PHP.
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        $metricEntities = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->orderByName()
            ->find();

        $collectionTransfer = new SearchRankingMetricCollectionTransfer();
        $mapper = $this->getFactory()->createSearchRankingMapper();

        foreach ($metricEntities as $metricEntity) {
            $metricTransfer = $mapper->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());
            $metricTransfer = $this->attachStoreConfig($metricTransfer, $storeName);

            if ($metricTransfer->getIsActive() !== true) {
                continue;
            }

            $collectionTransfer->addMetric($this->attachWeight($metricTransfer, $storeName, $localeName));
        }

        return $collectionTransfer;
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
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
        $metricTransfer = $this->attachStoreConfig($metricTransfer, $storeName);

        return $this->attachWeight($metricTransfer, $storeName, $localeName);
    }

    /**
     * @param string $name
     * @param string $storeName
     * @param string $localeName
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
        $metricTransfer = $this->attachStoreConfig($metricTransfer, $storeName);

        return $this->attachWeight($metricTransfer, $storeName, $localeName);
    }

    /**
     * Overlays $metricTransfer's formula/isActive/shape from its store-config row for $storeName — the
     * real, live per-store values. Same composable-overlay shape {@see attachWeight()} already
     * established. No store-config
     * row yet (a metric never configured for this store — e.g. one created via
     * `spryker-community/search-ranking-data-import`, or before this store existed) is a SAFE absence,
     * never an error: isActive=false so nothing downstream evaluates the (also null) formula, matching
     * this package's existing "absence = neutral default" convention for weight.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     */
    protected function attachStoreConfig(SearchRankingMetricTransfer $metricTransfer, string $storeName): SearchRankingMetricTransfer
    {
        $storeConfigTransfer = $this->findMetricStoreConfig($metricTransfer->getIdSearchRankingMetricOrFail(), $storeName);

        if ($storeConfigTransfer === null) {
            return $metricTransfer->setFormula(null)->setIsActive(false)->setShape(null);
        }

        return $metricTransfer
            ->setFormula($storeConfigTransfer->getFormula())
            ->setIsActive($storeConfigTransfer->getIsActive())
            ->setShape($storeConfigTransfer->getShape());
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
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

        $activeMetricIds = $this->findActiveMetricIdsForStore($storeName);

        if ($activeMetricIds === []) {
            return [];
        }

        $scoreRows = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByFkProductAbstract_In($productAbstractIds)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->filterByNormalizedValue(null, Criteria::ISNOTNULL)
            ->filterByFkSearchRankingMetric_In($activeMetricIds)
            ->useSearchRankingMetricQuery()
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
     * Deliberately store-agnostic — "does this product need a republish at all" for the PRODUCT_ABSTRACT_PUBLISH
     * event, not a per-store index view, so a metric active in ANY store (not just one) qualifies its
     * product. `is_active` lives on `spy_search_ranking_metric_store_config` (one row per store), so
     * this checks for at least one store where the metric is active — there is no single global flag.
     *
     * @return array<int>
     */
    public function getProductAbstractIdsWithActiveMetricValues(): array
    {
        $activeMetricIds = $this->getFactory()
            ->createSearchRankingMetricStoreConfigQuery()
            ->filterByIsActive(true)
            ->select([SpySearchRankingMetricStoreConfigTableMap::COL_FK_SEARCH_RANKING_METRIC])
            ->distinct()
            ->find()
            ->getData();

        if ($activeMetricIds === []) {
            return [];
        }

        /** @var array<int|string> $productAbstractIds */
        $productAbstractIds = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByNormalizedValue(null, Criteria::ISNOTNULL)
            ->filterByFkSearchRankingMetric_In($activeMetricIds)
            ->select([SpySearchRankingProductMetricTableMap::COL_FK_PRODUCT_ABSTRACT])
            ->distinct()
            ->find()
            ->getData();

        return array_map(static fn ($value): int => (int)$value, $productAbstractIds);
    }

    /**
     * @param string $storeName
     *
     * @return array<int>
     */
    protected function findActiveMetricIdsForStore(string $storeName): array
    {
        /** @var array<int|string> $activeMetricIds */
        $activeMetricIds = $this->getFactory()
            ->createSearchRankingMetricStoreConfigQuery()
            ->filterByStoreName($storeName)
            ->filterByIsActive(true)
            ->select([SpySearchRankingMetricStoreConfigTableMap::COL_FK_SEARCH_RANKING_METRIC])
            ->find()
            ->getData();

        return array_map(static fn ($value): int => (int)$value, $activeMetricIds);
    }

    /**
     * @param string $settingKey
     * @param string $storeName
     * @param string $localeName
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

    /**
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array
    {
        $scopeCopyLockEntities = $this->getFactory()
            ->createSearchRankingScopeCopyLockQuery()
            ->filterByIsActive(true)
            ->orderByIdSearchRankingScopeCopyLock(Criteria::DESC)
            ->find();

        return $this->mapScopeCopyLockEntities($scopeCopyLockEntities);
    }

    /**
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getAllScopeCopyLocks(): array
    {
        $scopeCopyLockEntities = $this->getFactory()
            ->createSearchRankingScopeCopyLockQuery()
            ->orderByIdSearchRankingScopeCopyLock(Criteria::DESC)
            ->find();

        return $this->mapScopeCopyLockEntities($scopeCopyLockEntities);
    }

    /**
     * @param int $idSearchRankingScopeCopyLock
     */
    public function findScopeCopyLockById(int $idSearchRankingScopeCopyLock): ?SearchRankingScopeCopyLockTransfer
    {
        $scopeCopyLockEntity = $this->getFactory()
            ->createSearchRankingScopeCopyLockQuery()
            ->findOneByIdSearchRankingScopeCopyLock($idSearchRankingScopeCopyLock);

        if ($scopeCopyLockEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createSearchRankingMapper()
            ->mapScopeCopyLockEntityToTransfer($scopeCopyLockEntity, new SearchRankingScopeCopyLockTransfer());
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     */
    public function findMetricStoreConfig(int $idSearchRankingMetric, string $storeName): ?SearchRankingMetricStoreConfigTransfer
    {
        $metricStoreConfigEntity = $this->getFactory()
            ->createSearchRankingMetricStoreConfigQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName($storeName)
            ->findOne();

        if ($metricStoreConfigEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createSearchRankingMapper()
            ->mapMetricStoreConfigEntityToTransfer($metricStoreConfigEntity, new SearchRankingMetricStoreConfigTransfer());
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function hasScopeConfiguration(string $storeName, string $localeName): bool
    {
        $hasMetricWeight = $this->getFactory()
            ->createSearchRankingMetricWeightQuery()
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->count() > 0;

        if ($hasMetricWeight) {
            return true;
        }

        return $this->getFactory()
            ->createSearchRankingSettingQuery()
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
            ->count() > 0;
    }

    /**
     * @param string $storeName
     */
    public function hasStoreConfiguration(string $storeName): bool
    {
        return $this->getFactory()
            ->createSearchRankingMetricStoreConfigQuery()
            ->filterByStoreName($storeName)
            ->count() > 0;
    }

    /**
     * @param iterable<\Orm\Zed\SearchRanking\Persistence\SpySearchRankingScopeCopyLock> $scopeCopyLockEntities
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    protected function mapScopeCopyLockEntities(iterable $scopeCopyLockEntities): array
    {
        $mapper = $this->getFactory()->createSearchRankingMapper();
        $scopeCopyLockTransfers = [];

        foreach ($scopeCopyLockEntities as $scopeCopyLockEntity) {
            $scopeCopyLockTransfers[] = $mapper->mapScopeCopyLockEntityToTransfer($scopeCopyLockEntity, new SearchRankingScopeCopyLockTransfer());
        }

        return $scopeCopyLockTransfers;
    }
}
