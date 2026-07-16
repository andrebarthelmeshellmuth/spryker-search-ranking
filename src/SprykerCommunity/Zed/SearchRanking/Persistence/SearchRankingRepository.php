<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingProductMetricTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;

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
}
