<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Table;

use Orm\Zed\Product\Persistence\Map\SpyProductAbstractTableMap;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingMetricTableMap;
use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingProductMetricTableMap;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;

class ProductMetricTable extends AbstractTable
{
    /**
     * @var string
     */
    protected const COL_METRIC_NAME = 'metric_name';

    /**
     * @var string
     */
    protected const COL_ABSTRACT_SKU = 'abstract_sku';

    /**
     * @var \Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery
     */
    protected SpySearchRankingProductMetricQuery $productMetricQuery;

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery $productMetricQuery
     */
    public function __construct(SpySearchRankingProductMetricQuery $productMetricQuery)
    {
        $this->productMetricQuery = $productMetricQuery;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return \Spryker\Zed\Gui\Communication\Table\TableConfiguration
     */
    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setHeader([
            SpySearchRankingProductMetricTableMap::COL_ID_SEARCH_RANKING_PRODUCT_METRIC => 'ID',
            static::COL_ABSTRACT_SKU => 'Abstract SKU',
            static::COL_METRIC_NAME => 'Metric',
            SpySearchRankingProductMetricTableMap::COL_RAW_VALUE => 'Raw Value',
            SpySearchRankingProductMetricTableMap::COL_NORMALIZED_VALUE => 'Normalized Value',
            SpySearchRankingProductMetricTableMap::COL_UPDATED_AT => 'Updated At',
        ]);

        $config->setSortable([
            SpySearchRankingProductMetricTableMap::COL_ID_SEARCH_RANKING_PRODUCT_METRIC,
            SpySearchRankingProductMetricTableMap::COL_RAW_VALUE,
            SpySearchRankingProductMetricTableMap::COL_NORMALIZED_VALUE,
            SpySearchRankingProductMetricTableMap::COL_UPDATED_AT,
        ]);

        $config->setSearchable([
            SpyProductAbstractTableMap::COL_SKU,
            SpySearchRankingMetricTableMap::COL_NAME,
        ]);

        $config->setDefaultSortField(SpySearchRankingProductMetricTableMap::COL_ID_SEARCH_RANKING_PRODUCT_METRIC);

        return $config;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $query = $this->productMetricQuery
            ->joinWithSearchRankingMetric()
            ->joinWithProductAbstract()
            ->withColumn(SpySearchRankingMetricTableMap::COL_NAME, static::COL_METRIC_NAME)
            ->withColumn(SpyProductAbstractTableMap::COL_SKU, static::COL_ABSTRACT_SKU);

        $productMetricRows = $this->runQuery($query, $config);
        $rows = [];

        foreach ($productMetricRows as $productMetricRow) {
            $rows[] = [
                SpySearchRankingProductMetricTableMap::COL_ID_SEARCH_RANKING_PRODUCT_METRIC => $productMetricRow[SpySearchRankingProductMetricTableMap::COL_ID_SEARCH_RANKING_PRODUCT_METRIC],
                static::COL_ABSTRACT_SKU => $productMetricRow[static::COL_ABSTRACT_SKU],
                static::COL_METRIC_NAME => $productMetricRow[static::COL_METRIC_NAME],
                SpySearchRankingProductMetricTableMap::COL_RAW_VALUE => $productMetricRow[SpySearchRankingProductMetricTableMap::COL_RAW_VALUE],
                SpySearchRankingProductMetricTableMap::COL_NORMALIZED_VALUE => $productMetricRow[SpySearchRankingProductMetricTableMap::COL_NORMALIZED_VALUE] ?? '-',
                SpySearchRankingProductMetricTableMap::COL_UPDATED_AT => $productMetricRow[SpySearchRankingProductMetricTableMap::COL_UPDATED_AT],
            ];
        }

        return $rows;
    }
}
