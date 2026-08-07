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
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

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
     * Unlike every other table in this GUI, storeName/localeName are OPTIONAL here — this table is a
     * cross-scope audit trail by design (an admin needs to see everything that changed, not just one
     * scope), so "no filter" (both null) is the useful default, not an error state. A metric fanning a
     * weight/formula change out across every locale of a store (see MetricWriter's fan-out) makes several
     * rows share the same metric/weight/timestamp — the Store/Locale columns below are what actually
     * distinguishes them, so they exist even when not filtering.
     *
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery $historyQuery
     * @param string|null $storeName
     * @param string|null $localeName
     */
    public function __construct(
        protected SpySearchRankingMetricHistoryQuery $historyQuery,
        protected ?string $storeName = null,
        protected ?string $localeName = null,
    ) {
        if ($this->storeName !== null) {
            $this->historyQuery->filterByStoreName($this->storeName);
        }

        if ($this->localeName === null) {
            return;
        }

        $this->historyQuery->filterByLocaleName($this->localeName);
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     */
    protected function configure(TableConfiguration $config): TableConfiguration
    {
        // The AJAX endpoint DataTables calls for every sort/page/search action is a fresh request that
        // never sees the request this Table was originally constructed for — the selected filter (if any)
        // has to be baked into that URL, same pattern MetricTable/ProductMetricTable already established.
        // Omitted entirely (not just empty) when unset, so "no filter" round-trips as "no filter" rather
        // than becoming an explicit-empty-string filter on the next AJAX call.
        $urlParams = array_filter([
            'storeName' => $this->storeName,
            'localeName' => $this->localeName,
        ], static fn ($value): bool => $value !== null);

        if ($urlParams !== []) {
            $config->setUrl('table?' . http_build_query($urlParams));
        }

        $config->setHeader([
            SpySearchRankingMetricHistoryTableMap::COL_ID_SEARCH_RANKING_METRIC_HISTORY => 'ID',
            SpySearchRankingMetricHistoryTableMap::COL_METRIC_NAME => 'Metric',
            SpySearchRankingMetricHistoryTableMap::COL_STORE_NAME => 'Store',
            SpySearchRankingMetricHistoryTableMap::COL_LOCALE_NAME => 'Locale',
            SpySearchRankingMetricHistoryTableMap::COL_FORMULA => 'Formula',
            SpySearchRankingMetricHistoryTableMap::COL_WEIGHT => 'Weight',
            SpySearchRankingMetricHistoryTableMap::COL_IS_ACTIVE => 'Active',
            static::COL_DIRECTION => 'Direction',
            static::COL_FIT => 'Fit (R²)',
            SpySearchRankingMetricHistoryTableMap::COL_IS_CHANGE => 'Type',
            SpySearchRankingMetricHistoryTableMap::COL_CHANGE_SOURCE => 'Source',
            SpySearchRankingMetricHistoryTableMap::COL_CREATED_AT => 'Recorded At',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSortable([
            SpySearchRankingMetricHistoryTableMap::COL_ID_SEARCH_RANKING_METRIC_HISTORY,
            SpySearchRankingMetricHistoryTableMap::COL_METRIC_NAME,
            SpySearchRankingMetricHistoryTableMap::COL_STORE_NAME,
            SpySearchRankingMetricHistoryTableMap::COL_LOCALE_NAME,
            SpySearchRankingMetricHistoryTableMap::COL_WEIGHT,
            SpySearchRankingMetricHistoryTableMap::COL_IS_ACTIVE,
            SpySearchRankingMetricHistoryTableMap::COL_FIT_R_SQUARED,
            SpySearchRankingMetricHistoryTableMap::COL_CHANGE_SOURCE,
            SpySearchRankingMetricHistoryTableMap::COL_CREATED_AT,
        ]);

        $config->setSearchable([
            SpySearchRankingMetricHistoryTableMap::COL_METRIC_NAME,
            SpySearchRankingMetricHistoryTableMap::COL_STORE_NAME,
            SpySearchRankingMetricHistoryTableMap::COL_LOCALE_NAME,
            SpySearchRankingMetricHistoryTableMap::COL_FORMULA,
            SpySearchRankingMetricHistoryTableMap::COL_CHANGE_SOURCE,
        ]);

        $config->setRawColumns([
            SpySearchRankingMetricHistoryTableMap::COL_IS_ACTIVE,
            static::COL_DIRECTION,
            SpySearchRankingMetricHistoryTableMap::COL_IS_CHANGE,
            SpySearchRankingMetricHistoryTableMap::COL_CHANGE_SOURCE,
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
                SpySearchRankingMetricHistoryTableMap::COL_STORE_NAME => $historyEntity->getStoreName(),
                SpySearchRankingMetricHistoryTableMap::COL_LOCALE_NAME => $historyEntity->getLocaleName(),
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
                    $historyEntity->getIsChange() ? 'label-success' : 'label-secondary',
                ),
                SpySearchRankingMetricHistoryTableMap::COL_CHANGE_SOURCE => $this->formatChangeSource($historyEntity->getChangeSource()),
                SpySearchRankingMetricHistoryTableMap::COL_CREATED_AT => $historyEntity->getCreatedAt('Y-m-d H:i:s'),
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
     */
    protected function formatFit(?float $fitRSquared): string
    {
        return $fitRSquared === null ? '—' : number_format($fitRSquared, 3);
    }

    /**
     * Human-readable label for one of {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig}::CHANGE_SOURCE_*
     * — a neutral-styled label for the base package's own MANUAL source (nothing to call out), and a
     * distinct "info" style for every automated source, so an admin scanning the table can visually spot
     * a machine-originated row at a glance rather than reading each cell. Falls back to the raw stored
     * value for a row written before this label list existed or by a future, not-yet-known source.
     *
     * @param string|null $changeSource
     */
    protected function formatChangeSource(?string $changeSource): string
    {
        $labelsByChangeSource = [
            SharedSearchRankingConfig::CHANGE_SOURCE_MANUAL => 'Manual',
            SharedSearchRankingConfig::CHANGE_SOURCE_AUTO_TUNE => 'Auto-Tune',
            SharedSearchRankingConfig::CHANGE_SOURCE_OPTIMIZER_APPLY => 'Optimizer apply',
            SharedSearchRankingConfig::CHANGE_SOURCE_CHECKPOINT_RESTORE => 'Checkpoint restore',
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY => 'Scope copy',
        ];

        $label = $labelsByChangeSource[$changeSource] ?? $changeSource ?? 'Manual';
        $cssClass = $changeSource === SharedSearchRankingConfig::CHANGE_SOURCE_MANUAL || $changeSource === null
            ? 'label-secondary'
            : 'label-info';

        return $this->generateLabel($label, $cssClass);
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
