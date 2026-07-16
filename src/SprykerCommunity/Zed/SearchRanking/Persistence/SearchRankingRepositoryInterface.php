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

interface SearchRankingRepositoryInterface
{
    /**
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getMetricCollection(): SearchRankingMetricCollectionTransfer;

    /**
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(): SearchRankingMetricCollectionTransfer;

    /**
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric): ?SearchRankingMetricTransfer;

    /**
     * @param string $name
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer;

    /**
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer
     */
    public function getMetricStatistics(int $idSearchRankingMetric): SearchRankingMetricStatisticsTransfer;

    /**
     * Result rows are ordered by id ascending; pass the last seen id to page through the full set.
     *
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
    ): array;
}
