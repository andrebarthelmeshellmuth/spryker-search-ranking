<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer;
use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingMetricTableMap;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingProductMetricTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingPersistenceFactory getFactory()
 */
class SearchRankingRepository extends AbstractRepository implements SearchRankingRepositoryInterface
{
    /**
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getMetricCollection(): SearchRankingMetricCollectionTransfer
    {
        $metricEntities = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->orderByName()
            ->find();

        $collectionTransfer = new SearchRankingMetricCollectionTransfer();
        $mapper = $this->getFactory()->createSearchRankingMapper();

        foreach ($metricEntities as $metricEntity) {
            $collectionTransfer->addMetric(
                $mapper->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer()),
            );
        }

        return $collectionTransfer;
    }

    /**
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(): SearchRankingMetricCollectionTransfer
    {
        $metricEntities = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->filterByIsActive(true)
            ->orderByName()
            ->find();

        $collectionTransfer = new SearchRankingMetricCollectionTransfer();
        $mapper = $this->getFactory()->createSearchRankingMapper();

        foreach ($metricEntities as $metricEntity) {
            $collectionTransfer->addMetric(
                $mapper->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer()),
            );
        }

        return $collectionTransfer;
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric): ?SearchRankingMetricTransfer
    {
        $metricEntity = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->findOneByIdSearchRankingMetric($idSearchRankingMetric);

        if ($metricEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createSearchRankingMapper()
            ->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());
    }

    /**
     * @param string $name
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer
    {
        $metricEntity = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->findOneByName($name);

        if ($metricEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createSearchRankingMapper()
            ->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer
     */
    public function getMetricStatistics(int $idSearchRankingMetric): SearchRankingMetricStatisticsTransfer
    {
        /** @var array<string, mixed>|null $statisticsRow */
        $statisticsRow = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
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
     * @param int $idLastSearchRankingProductMetric
     * @param int $limit
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingProductMetricTransfer>
     */
    public function getProductMetricBatch(
        int $idSearchRankingMetric,
        int $idLastSearchRankingProductMetric,
        int $limit,
    ): array {
        $productMetricEntities = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
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
     *
     * @return array<int, array<string, float>>
     */
    public function getNormalizedScoresGroupedByIdProductAbstract(array $productAbstractIds): array
    {
        if ($productAbstractIds === []) {
            return [];
        }

        $scoreRows = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByFkProductAbstract_In($productAbstractIds)
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

        return array_map('intval', $productAbstractIds);
    }

    /**
     * @param string $settingKey
     *
     * @return string|null
     */
    public function findSettingValue(string $settingKey): ?string
    {
        $settingEntity = $this->getFactory()
            ->createSearchRankingSettingQuery()
            ->findOneBySettingKey($settingKey);

        return $settingEntity?->getSettingValue();
    }

    /**
     * @return array<\Generated\Shared\Transfer\SearchRankingCalibrationTransfer>
     */
    public function getUploadedCalibrations(): array
    {
        $calibrationEntities = $this->getFactory()
            ->createSearchRankingCalibrationQuery()
            ->filterByStatus(SearchRankingConfig::CALIBRATION_STATUS_UPLOADED)
            ->orderByIdSearchRankingCalibration(Criteria::DESC)
            ->find();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $calibrationTransfers = [];

        foreach ($calibrationEntities as $calibrationEntity) {
            $calibrationTransfers[] = $mapper->mapCalibrationEntityToTransfer($calibrationEntity, new SearchRankingCalibrationTransfer());
        }

        return $calibrationTransfers;
    }

    /**
     * @param int $idSearchRankingCalibration
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer|null
     */
    public function findCalibrationWithSearchTerms(int $idSearchRankingCalibration): ?SearchRankingCalibrationTransfer
    {
        $calibrationEntity = $this->getFactory()
            ->createSearchRankingCalibrationQuery()
            ->findOneByIdSearchRankingCalibration($idSearchRankingCalibration);

        if ($calibrationEntity === null) {
            return null;
        }

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $calibrationTransfer = $mapper->mapCalibrationEntityToTransfer($calibrationEntity, new SearchRankingCalibrationTransfer());

        $searchTermEntities = $this->getFactory()
            ->createSearchRankingCalibrationSearchTermQuery()
            ->filterByFkSearchRankingCalibration($idSearchRankingCalibration)
            ->orderByIdSearchRankingCalibrationSearchTerm()
            ->find();

        foreach ($searchTermEntities as $searchTermEntity) {
            $calibrationTransfer->addSearchTerm(
                $mapper->mapCalibrationSearchTermEntityToTransfer($searchTermEntity, new SearchRankingCalibrationSearchTermTransfer()),
            );
        }

        return $calibrationTransfer;
    }

    /**
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer|null
     */
    public function findLatestCalculatedCalibration(): ?SearchRankingCalibrationTransfer
    {
        $calibrationEntity = $this->getFactory()
            ->createSearchRankingCalibrationQuery()
            ->filterByStatus(SearchRankingConfig::CALIBRATION_STATUS_CALCULATED)
            ->orderByCalculatedAt(Criteria::DESC)
            ->findOne();

        if ($calibrationEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createSearchRankingMapper()
            ->mapCalibrationEntityToTransfer($calibrationEntity, new SearchRankingCalibrationTransfer());
    }
}
