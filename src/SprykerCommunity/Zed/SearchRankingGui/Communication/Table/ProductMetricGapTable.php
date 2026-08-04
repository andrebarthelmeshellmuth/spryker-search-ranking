<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Table;

use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
use SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapFinderInterface;

/**
 * Talks to `ProductMetricGapFinderInterface` directly (raw SQL under the hood — see that class' own
 * docblock) instead of `AbstractTable::runQuery()`, which only knows how to page/sort/search a Propel
 * `ModelCriteria`. Search, sort, and pagination are reimplemented here against the finder's own
 * parameters instead — the same DataTables request parameters `runQuery()` would have read, just applied
 * by hand.
 */
class ProductMetricGapTable extends AbstractTable
{
    /**
     * @var string
     */
    protected const COL_SKU = 'sku';

    /**
     * @var string
     */
    protected const COL_MISSING_METRIC_NAME = 'missing_metric_name';

    /**
     * @var string
     */
    protected const URL_PARAM_METRIC = 'metric';

    /**
     * @var string
     */
    protected const URL_PARAM_STORE_NAME = 'storeName';

    /**
     * @var string
     */
    protected const URL_PARAM_LOCALE_NAME = 'localeName';

    /**
     * @param \SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapFinderInterface $productMetricGapFinder
     * @param int|null $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function __construct(
        protected ProductMetricGapFinderInterface $productMetricGapFinder,
        protected ?int $idSearchRankingMetric,
        protected string $storeName,
        protected string $localeName,
    ) {
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     */
    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setHeader([
            static::COL_SKU => 'Abstract SKU',
            static::COL_MISSING_METRIC_NAME => 'Missing Business Score',
        ]);

        $config->setSortable([
            static::COL_SKU,
            static::COL_MISSING_METRIC_NAME,
        ]);

        $config->setSearchable([
            static::COL_SKU,
            static::COL_MISSING_METRIC_NAME,
        ]);

        $config->setDefaultSortField(static::COL_SKU);

        // Same reasoning as before: the AJAX endpoint DataTables calls for every sort/page/search action
        // is a fresh request that never sees the request this Table was originally constructed for — the
        // selected metric (if any) and the selected scope both have to be baked into that URL. `setUrl()`
        // takes a fragment RELATIVE to the auto-derived `/{module}/{controller}/` base (the framework's
        // own default is the bare string 'table') — passing an absolute path here would be appended after
        // that base, not replace it.
        $tableUrl = sprintf(
            'table?%s=%s&%s=%s',
            static::URL_PARAM_STORE_NAME,
            urlencode($this->storeName),
            static::URL_PARAM_LOCALE_NAME,
            urlencode($this->localeName),
        );

        if ($this->idSearchRankingMetric !== null) {
            $tableUrl .= sprintf('&%s=%d', static::URL_PARAM_METRIC, $this->idSearchRankingMetric);
        }

        $config->setUrl($tableUrl);

        return $config;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $this->setTotal($this->productMetricGapFinder->countGaps($this->idSearchRankingMetric, $this->storeName, $this->localeName));

        $searchTerm = (string)($this->getSearchTerm()[static::PARAMETER_VALUE] ?? '');

        $this->setFiltered(
            $searchTerm === ''
                ? $this->total
                : $this->productMetricGapFinder->countFilteredGaps($this->idSearchRankingMetric, $this->storeName, $this->localeName, $searchTerm),
        );

        [$sortColumn, $sortDirection] = $this->resolveSort($config);

        $gapRows = $this->productMetricGapFinder->findGaps(
            $this->idSearchRankingMetric,
            $this->storeName,
            $this->localeName,
            $searchTerm,
            $sortColumn,
            $sortDirection,
            $this->getLimit(),
            $this->getOffset(),
        );

        $rows = [];

        foreach ($gapRows as $gapRow) {
            $rows[] = [
                static::COL_SKU => $gapRow['sku'],
                static::COL_MISSING_METRIC_NAME => $gapRow['missing_metric_name'],
            ];
        }

        return $rows;
    }

    /**
     * Mirrors `AbstractTable::getOrderByColumn()`'s own logic (column index -> header key, whitelisted
     * against `setSortable()`) without needing the `ModelCriteria` parameter that method requires.
     *
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(TableConfiguration $config): array
    {
        $order = $this->getOrders($config)[0] ?? null;
        $headerKeys = array_keys($config->getHeader());
        $columnIndex = $order[static::SORT_BY_COLUMN] ?? 0;
        $column = $headerKeys[$columnIndex] ?? static::COL_SKU;

        if (!in_array($column, $config->getSortable(), true)) {
            $column = static::COL_SKU;
        }

        $direction = $order[static::SORT_BY_DIRECTION] ?? TableConfiguration::SORT_ASC;

        return [$column, $direction];
    }
}
