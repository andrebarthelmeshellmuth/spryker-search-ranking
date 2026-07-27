<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Table;

use Orm\Zed\Product\Persistence\Map\SpyProductAbstractTableMap;
use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
use SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapQueryBuilderInterface;

class ProductMetricGapTable extends AbstractTable
{
    /**
     * @var string
     */
    protected const URL_PARAM_METRIC = 'metric';

    /**
     * @var \Orm\Zed\Product\Persistence\SpyProductAbstractQuery
     */
    protected SpyProductAbstractQuery $productAbstractQuery;

    /**
     * @var \SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapQueryBuilderInterface
     */
    protected ProductMetricGapQueryBuilderInterface $queryBuilder;

    /**
     * @var int|null
     */
    protected ?int $idSearchRankingMetric;

    /**
     * @param \Orm\Zed\Product\Persistence\SpyProductAbstractQuery $productAbstractQuery
     * @param \SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapQueryBuilderInterface $queryBuilder
     * @param int|null $idSearchRankingMetric
     */
    public function __construct(
        SpyProductAbstractQuery $productAbstractQuery,
        ProductMetricGapQueryBuilderInterface $queryBuilder,
        ?int $idSearchRankingMetric,
    ) {
        $this->productAbstractQuery = $productAbstractQuery;
        $this->queryBuilder = $queryBuilder;
        $this->idSearchRankingMetric = $idSearchRankingMetric;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return \Spryker\Zed\Gui\Communication\Table\TableConfiguration
     */
    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setHeader([
            SpyProductAbstractTableMap::COL_ID_PRODUCT_ABSTRACT => 'ID',
            SpyProductAbstractTableMap::COL_SKU => 'Abstract SKU',
        ]);

        $config->setSortable([
            SpyProductAbstractTableMap::COL_ID_PRODUCT_ABSTRACT,
            SpyProductAbstractTableMap::COL_SKU,
        ]);

        $config->setSearchable([
            SpyProductAbstractTableMap::COL_SKU,
        ]);

        $config->setDefaultSortField(SpyProductAbstractTableMap::COL_ID_PRODUCT_ABSTRACT);

        // The AJAX endpoint DataTables calls for every sort/page/search action is a fresh request that
        // never sees the request this Table was originally constructed for — the selected metric has to
        // be baked into that URL, or the filter would silently reset to nothing on the first page click.
        // `setUrl()` takes a fragment RELATIVE to the auto-derived `/{module}/{controller}/` base (the
        // framework's own default is the bare string 'table') — passing an absolute path here would be
        // appended after that base, not replace it.
        if ($this->idSearchRankingMetric !== null) {
            $config->setUrl(sprintf('table?%s=%d', static::URL_PARAM_METRIC, $this->idSearchRankingMetric));
        }

        return $config;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        if ($this->idSearchRankingMetric === null) {
            return [];
        }

        $query = $this->queryBuilder->filterMissingMetricValue($this->productAbstractQuery, $this->idSearchRankingMetric);

        $productAbstractRows = $this->runQuery($query, $config);
        $rows = [];

        foreach ($productAbstractRows as $productAbstractRow) {
            $rows[] = [
                SpyProductAbstractTableMap::COL_ID_PRODUCT_ABSTRACT => $productAbstractRow[SpyProductAbstractTableMap::COL_ID_PRODUCT_ABSTRACT],
                SpyProductAbstractTableMap::COL_SKU => $productAbstractRow[SpyProductAbstractTableMap::COL_SKU],
            ];
        }

        return $rows;
    }
}
