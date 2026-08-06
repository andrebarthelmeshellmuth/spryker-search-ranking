<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Table;

use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingMetricTableMap;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfigQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricWeightQuery;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;

class MetricTable extends AbstractTable
{
    /**
     * @var string
     */
    public const URL_PARAM_ID_SEARCH_RANKING_METRIC = 'id-search-ranking-metric';

    /**
     * Not a real column on spy_search_ranking_metric any more (weight moved to
     * spy_search_ranking_metric_weight, one row per store+locale) — kept as a plain array key so this
     * column can still be displayed (looked up separately in {@see prepareData()}), just no longer
     * sortable at the DB level via this table's own query.
     *
     * @var string
     */
    protected const COL_WEIGHT = 'weight';

    /**
     * Not real columns on spy_search_ranking_metric any more — moved to
     * spy_search_ranking_metric_store_config, one row per store, looked up separately in
     * {@see prepareData()}. Same "plain array key, not a real column" shape {@see COL_WEIGHT} already
     * established.
     *
     * @var string
     */
    protected const COL_FORMULA = 'formula';

    /**
     * @var string
     */
    protected const COL_IS_ACTIVE = 'is_active';

    /**
     * @var string
     */
    protected const COL_ACTIONS = 'actions';

    protected SpySearchRankingMetricQuery $metricQuery;

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery $metricQuery
     * @param string $storeName
     * @param string $localeName
     */
    public function __construct(
        SpySearchRankingMetricQuery $metricQuery,
        protected string $storeName,
        protected string $localeName,
    ) {
        $this->metricQuery = $metricQuery;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     */
    protected function configure(TableConfiguration $config): TableConfiguration
    {
        // The AJAX endpoint DataTables calls for every sort/page/search action is a fresh request that
        // never sees the request this Table was originally constructed for — the selected scope has to
        // be baked into that URL (same pattern ProductMetricGapTable already established for its own
        // `?metric=` filter).
        $config->setUrl(sprintf(
            'table?storeName=%s&localeName=%s',
            urlencode($this->storeName),
            urlencode($this->localeName),
        ));

        $config->setHeader([
            SpySearchRankingMetricTableMap::COL_ID_SEARCH_RANKING_METRIC => 'ID',
            SpySearchRankingMetricTableMap::COL_NAME => 'Name',
            static::COL_WEIGHT => 'Weight',
            static::COL_FORMULA => 'Formula',
            static::COL_IS_ACTIVE => 'Active',
            static::COL_ACTIONS => 'Actions',
        ]);

        // is_active/formula are not real columns on THIS table's own query any more — moved to
        // spy_search_ranking_metric_store_config, looked up separately below, same plain-array-key shape
        // COL_WEIGHT's own comment already explains for weight — so dropped from sortable/searchable
        // here rather than letting DB-level sort/search silently operate on stale, frozen data.
        $config->setSortable([
            SpySearchRankingMetricTableMap::COL_ID_SEARCH_RANKING_METRIC,
            SpySearchRankingMetricTableMap::COL_NAME,
        ]);

        $config->setSearchable([
            SpySearchRankingMetricTableMap::COL_NAME,
        ]);

        $config->setRawColumns([
            static::COL_IS_ACTIVE,
            static::COL_ACTIONS,
        ]);

        $config->setDefaultSortField(SpySearchRankingMetricTableMap::COL_NAME);

        return $config;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $metricEntities = $this->runQuery($this->metricQuery, $config, true);
        $weightsById = $this->findWeightsById($metricEntities);
        $storeConfigsById = $this->findStoreConfigsById($metricEntities);
        $rows = [];

        /** @var \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity */
        foreach ($metricEntities as $metricEntity) {
            $idSearchRankingMetric = $metricEntity->getIdSearchRankingMetric();
            $storeConfigEntity = $storeConfigsById[$idSearchRankingMetric] ?? null;
            $isActive = $storeConfigEntity !== null && $storeConfigEntity->getIsActive();

            $rows[] = [
                SpySearchRankingMetricTableMap::COL_ID_SEARCH_RANKING_METRIC => $idSearchRankingMetric,
                SpySearchRankingMetricTableMap::COL_NAME => $metricEntity->getName(),
                static::COL_WEIGHT => $weightsById[$idSearchRankingMetric] ?? 0.0,
                static::COL_FORMULA => $storeConfigEntity?->getFormula(),
                static::COL_IS_ACTIVE => $this->generateLabel(
                    $isActive ? 'Active' : 'Inactive',
                    $isActive ? 'label-info' : 'label-danger',
                ),
                static::COL_ACTIONS => implode(' ', $this->createActionButtons($metricEntity)),
            ];
        }

        return $rows;
    }

    /**
     * @param iterable<\Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric> $metricEntities
     *
     * @return array<int, float>
     */
    protected function findWeightsById(iterable $metricEntities): array
    {
        $idSearchRankingMetrics = [];

        foreach ($metricEntities as $metricEntity) {
            $idSearchRankingMetrics[] = $metricEntity->getIdSearchRankingMetric();
        }

        if ($idSearchRankingMetrics === []) {
            return [];
        }

        $weightsById = [];

        foreach (
            SpySearchRankingMetricWeightQuery::create()
                ->filterByFkSearchRankingMetric_In($idSearchRankingMetrics)
                ->filterByStoreName($this->storeName)
                ->filterByLocaleName($this->localeName)
                ->find() as $metricWeightEntity
        ) {
            $weightsById[$metricWeightEntity->getFkSearchRankingMetric()] = $metricWeightEntity->getWeight();
        }

        return $weightsById;
    }

    /**
     * formula/isActive live here — same "looked up separately, not a real column on this table's own
     * query" shape {@see findWeightsById()} already established for weight. No row for a given metric (never
     * configured for this store) is a real, expected absence — {@see prepareData()} treats it as
     * inactive/no formula, the same safe fallback the repository's own `attachStoreConfig()` uses.
     *
     * @param iterable<\Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric> $metricEntities
     *
     * @return array<int, \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfig>
     */
    protected function findStoreConfigsById(iterable $metricEntities): array
    {
        $idSearchRankingMetrics = [];

        foreach ($metricEntities as $metricEntity) {
            $idSearchRankingMetrics[] = $metricEntity->getIdSearchRankingMetric();
        }

        if ($idSearchRankingMetrics === []) {
            return [];
        }

        $storeConfigsById = [];

        foreach (
            SpySearchRankingMetricStoreConfigQuery::create()
                ->filterByFkSearchRankingMetric_In($idSearchRankingMetrics)
                ->filterByStoreName($this->storeName)
                ->find() as $storeConfigEntity
        ) {
            $storeConfigsById[$storeConfigEntity->getFkSearchRankingMetric()] = $storeConfigEntity;
        }

        return $storeConfigsById;
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity
     *
     * @return array<string>
     */
    protected function createActionButtons(SpySearchRankingMetric $metricEntity): array
    {
        $urlParams = [
            static::URL_PARAM_ID_SEARCH_RANKING_METRIC => $metricEntity->getIdSearchRankingMetric(),
            'storeName' => $this->storeName,
            'localeName' => $this->localeName,
        ];

        return [
            $this->generateEditButton(
                Url::generate('/search-ranking-gui/edit', $urlParams)->build(),
                'Edit',
            ),
            $this->generateRemoveButton(
                Url::generate('/search-ranking-gui/delete', $urlParams)->build(),
                'Delete',
            ),
        ];
    }
}
