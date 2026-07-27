<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Persistence;

use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingProductMetricTableMap;
use Propel\Runtime\ActiveQuery\Criteria;

class ProductMetricGapQueryBuilder implements ProductMetricGapQueryBuilderInterface
{
    /**
     * The relation name Propel registers the join under when no alias is given — matches the FK's
     * `phpName` in `spy_search_ranking.schema.xml`, needed to reference the same join in
     * `addJoinCondition()` below.
     *
     * @var string
     */
    protected const JOIN_NAME_SEARCH_RANKING_PRODUCT_METRIC = 'SearchRankingProductMetric';

    /**
     * {@inheritDoc}
     *
     * @param \Orm\Zed\Product\Persistence\SpyProductAbstractQuery $productAbstractQuery
     * @param int $idSearchRankingMetric
     *
     * @return \Orm\Zed\Product\Persistence\SpyProductAbstractQuery
     */
    public function filterMissingMetricValue(
        SpyProductAbstractQuery $productAbstractQuery,
        int $idSearchRankingMetric,
    ): SpyProductAbstractQuery {
        $productAbstractQuery
            ->joinSearchRankingProductMetric(null, Criteria::LEFT_JOIN)
            ->addJoinCondition(
                static::JOIN_NAME_SEARCH_RANKING_PRODUCT_METRIC,
                SpySearchRankingProductMetricTableMap::COL_FK_SEARCH_RANKING_METRIC . ' = ?',
                $idSearchRankingMetric,
            )
            ->add(SpySearchRankingProductMetricTableMap::COL_ID_SEARCH_RANKING_PRODUCT_METRIC, null, Criteria::ISNULL);

        return $productAbstractQuery;
    }
}
