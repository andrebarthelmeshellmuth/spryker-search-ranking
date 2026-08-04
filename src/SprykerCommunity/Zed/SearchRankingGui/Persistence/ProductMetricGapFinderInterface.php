<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Persistence;

interface ProductMetricGapFinderInterface
{
    /**
     * Specification:
     * - Returns one row per (product abstract, active metric) pair with NO `spy_search_ranking_product_metric`
     *   row at all for the given store and locale — a gap: this product was never assigned a raw value for
     *   this business score in this scope, one way or another (never imported, or imported then deleted).
     *   Each row: `id_product_abstract`, `sku`, `missing_metric_name`.
     * - `$idSearchRankingMetric === null` returns gaps across EVERY active metric (the default view);
     *   passing a real ID restricts to that one metric only.
     * - `$searchTerm` matches against SKU or the metric name (case-insensitive substring), empty string
     *   matches everything.
     * - `$sortColumn` accepts only `'sku'` or `'missing_metric_name'` — anything else falls back to `sku`,
     *   never interpolated into SQL directly. Same for `$sortDirection` (`'asc'`/`'desc'` only).
     *
     * @api
     *
     * @param int|null $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param string $searchTerm
     * @param string $sortColumn
     * @param string $sortDirection
     * @param int $limit
     * @param int $offset
     *
     * @return array<int, array{id_product_abstract: int, sku: string, missing_metric_name: string}>
     */
    public function findGaps(
        ?int $idSearchRankingMetric,
        string $storeName,
        string $localeName,
        string $searchTerm,
        string $sortColumn,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array;

    /**
     * Specification:
     * - Total number of gap rows for the given metric filter (or across all active metrics when `null`)
     *   in the given store and locale, ignoring any search term — DataTables' `recordsTotal`.
     *
     * @api
     *
     * @param int|null $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return int
     */
    public function countGaps(?int $idSearchRankingMetric, string $storeName, string $localeName): int;

    /**
     * Specification:
     * - Number of gap rows matching both the metric filter (or all active metrics when `null`) AND the
     *   given search term, in the given store and locale — DataTables' `recordsFiltered`.
     *
     * @api
     *
     * @param int|null $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param string $searchTerm
     *
     * @return int
     */
    public function countFilteredGaps(?int $idSearchRankingMetric, string $storeName, string $localeName, string $searchTerm): int;
}
