<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Table;

use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingMetricHistoryTableMap;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;

/**
 * Read-only, newest-first view of every recorded metric config change — see
 * spy_search_ranking_metric_history's schema docs for what triggers a row and why it is append-only.
 */
class MetricHistoryTable extends AbstractTable
{
    /**
     * @var string
     */
    public const URL_PARAM_ID_SEARCH_RANKING_METRIC = 'id-search-ranking-metric';

    /**
     * @var string
     */
    protected const COL_DIRECTION = 'direction';

    /**
     * @var string
     */
    protected const COL_FIT = 'fit';

    /**
     * @var string
     */
    protected const COL_ACTIONS = 'actions';

    /**
     * @var \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery
     */
    protected SpySearchRankingMetricHistoryQuery $historyQuery;

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery $historyQuery
     */
    public function __construct(SpySearchRankingMetricHistoryQuery $historyQuery)
    {
        $this->historyQuery = $historyQuery;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return \Spryker\Zed\Gui\Communication\Table\TableConfiguration
     */
    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setHeader([
            SpySearchRankingMetricHistoryTableMap::COL_ID_SEARCH_RANKING_METRIC_HISTORY => 'ID',
            SpySearchRankingMetricHistoryTableMap::COL_METRIC_NAME => 'Metric',
            SpySearchRankingMetricHistoryTableMap::COL_FORMULA => 'Formula',
            SpySearchRankingMetricHistoryTableMap::COL_WEIGHT => 'Weight',
            SpySearchRankingMetricHistoryTableMap::COL_IS_ACTIVE => 'Active',
            static::COL_DIRECTION => 'Direction',
            static::COL_FIT => 'Fit (R²)',
            SpySearchRankingMetricHistoryTableMap::COL_IS_CHANGE => 'Type',
            SpySearchRankingMetricHistoryTableMap::COL_CREATED_AT => 'Recorded At',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSortable([
            SpySearchRankingMetricHistoryTableMap::COL_ID_SEARCH_RANKING_METRIC_HISTORY,
            SpySearchRankingMetricHistoryTableMap::COL_METRIC_NAME,
            SpySearchRankingMetricHistoryTableMap::COL_WEIGHT,
            SpySearchRankingMetricHistoryTableMap::COL_IS_ACTIVE,
            SpySearchRankingMetricHistoryTableMap::COL_FIT_R_SQUARED,
            SpySearchRankingMetricHistoryTableMap::COL_CREATED_AT,
        ]);

        $config->setSearchable([
            SpySearchRankingMetricHistoryTableMap::COL_METRIC_NAME,
            SpySearchRankingMetricHistoryTableMap::COL_FORMULA,
        ]);

        $config->setRawColumns([
            SpySearchRankingMetricHistoryTableMap::COL_IS_ACTIVE,
            static::COL_DIRECTION,
            SpySearchRankingMetricHistoryTableMap::COL_IS_CHANGE,
            static::COL_ACTIONS,
        ]);

        $config->setDefaultSortField(
            SpySearchRankingMetricHistoryTableMap::COL_ID_SEARCH_RANKING_METRIC_HISTORY,
            TableConfiguration::SORT_DESC,
        );

        return $config;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $historyEntities = $this->runQuery($this->historyQuery, $config, true);
        $rows = [];

        /** @var \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory $historyEntity */
        foreach ($historyEntities as $historyEntity) {
            $rows[] = [
                SpySearchRankingMetricHistoryTableMap::COL_ID_SEARCH_RANKING_METRIC_HISTORY => $historyEntity->getIdSearchRankingMetricHistory(),
                SpySearchRankingMetricHistoryTableMap::COL_METRIC_NAME => $historyEntity->getMetricName(),
                SpySearchRankingMetricHistoryTableMap::COL_FORMULA => $historyEntity->getFormula(),
                SpySearchRankingMetricHistoryTableMap::COL_WEIGHT => $historyEntity->getWeight(),
                SpySearchRankingMetricHistoryTableMap::COL_IS_ACTIVE => $this->generateLabel(
                    $historyEntity->getIsActive() ? 'Active' : 'Inactive',
                    $historyEntity->getIsActive() ? 'label-info' : 'label-danger',
                ),
                static::COL_DIRECTION => $historyEntity->getIsHigherBetter() ? 'Higher is better' : 'Lower is better',
                static::COL_FIT => $this->formatFit($historyEntity->getFitRSquared()),
                SpySearchRankingMetricHistoryTableMap::COL_IS_CHANGE => $this->generateLabel(
                    $historyEntity->getIsChange() ? 'Change' : 'Check only',
                    $historyEntity->getIsChange() ? 'label-success' : 'label-default',
                ),
                SpySearchRankingMetricHistoryTableMap::COL_CREATED_AT => $historyEntity->getCreatedAt(),
                static::COL_ACTIONS => implode(' ', $this->createActionButtons($historyEntity)),
            ];
        }

        return $rows;
    }

    /**
     * Null means no digest existed yet at snapshot time (e.g. the metric's very first history row, before
     * search-ranking:normalize had ever run for it) — shown as a plain dash rather than a misleading 0.
     *
     * @param float|null $fitRSquared
     *
     * @return string
     */
    protected function formatFit(?float $fitRSquared): string
    {
        return $fitRSquared === null ? '—' : number_format($fitRSquared, 3);
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory $historyEntity
     *
     * @return array<string>
     */
    protected function createActionButtons(SpySearchRankingMetricHistory $historyEntity): array
    {
        $urlParams = [
            static::URL_PARAM_ID_SEARCH_RANKING_METRIC => $historyEntity->getFkSearchRankingMetric(),
        ];

        return [
            $this->generateViewButton(
                Url::generate('/search-ranking-gui/edit', $urlParams)->build(),
                'View metric',
            ),
        ];
    }
}
