<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Persistence;

use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;

interface ProductMetricGapQueryBuilderInterface
{
    /**
     * Specification:
     * - Restricts the given product abstract query to only the abstracts that have NO
     *   `spy_search_ranking_product_metric` row at all for the given metric — a gap: this product was
     *   never assigned a raw value for this business score, one way or another (never imported, or
     *   imported then deleted).
     * - Implemented as a LEFT JOIN + `IS NULL` on the joined row, not a `NOT IN` subquery — scales as a
     *   single query regardless of how many (or few) product-metric rows already exist.
     *
     * @api
     *
     * @param \Orm\Zed\Product\Persistence\SpyProductAbstractQuery $productAbstractQuery
     * @param int $idSearchRankingMetric
     *
     * @return \Orm\Zed\Product\Persistence\SpyProductAbstractQuery
     */
    public function filterMissingMetricValue(
        SpyProductAbstractQuery $productAbstractQuery,
        int $idSearchRankingMetric,
    ): SpyProductAbstractQuery;
}
