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
     * @var string
     */
    protected const COL_ACTIONS = 'actions';

    /**
     * @var \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery
     */
    protected SpySearchRankingMetricQuery $metricQuery;

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery $metricQuery
     */
    public function __construct(SpySearchRankingMetricQuery $metricQuery)
    {
        $this->metricQuery = $metricQuery;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return \Spryker\Zed\Gui\Communication\Table\TableConfiguration
     */
    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setHeader([
            SpySearchRankingMetricTableMap::COL_ID_SEARCH_RANKING_METRIC => 'ID',
            SpySearchRankingMetricTableMap::COL_NAME => 'Name',
            SpySearchRankingMetricTableMap::COL_WEIGHT => 'Weight',
            SpySearchRankingMetricTableMap::COL_FORMULA => 'Formula',
            SpySearchRankingMetricTableMap::COL_IS_ACTIVE => 'Active',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSortable([
            SpySearchRankingMetricTableMap::COL_ID_SEARCH_RANKING_METRIC,
            SpySearchRankingMetricTableMap::COL_NAME,
            SpySearchRankingMetricTableMap::COL_WEIGHT,
            SpySearchRankingMetricTableMap::COL_IS_ACTIVE,
        ]);

        $config->setSearchable([
            SpySearchRankingMetricTableMap::COL_NAME,
            SpySearchRankingMetricTableMap::COL_FORMULA,
        ]);

        $config->setRawColumns([
            SpySearchRankingMetricTableMap::COL_IS_ACTIVE,
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
        $rows = [];

        /** @var \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity */
        foreach ($metricEntities as $metricEntity) {
            $rows[] = [
                SpySearchRankingMetricTableMap::COL_ID_SEARCH_RANKING_METRIC => $metricEntity->getIdSearchRankingMetric(),
                SpySearchRankingMetricTableMap::COL_NAME => $metricEntity->getName(),
                SpySearchRankingMetricTableMap::COL_WEIGHT => $metricEntity->getWeight(),
                SpySearchRankingMetricTableMap::COL_FORMULA => $metricEntity->getFormula(),
                SpySearchRankingMetricTableMap::COL_IS_ACTIVE => $this->generateLabel(
                    $metricEntity->getIsActive() ? 'Active' : 'Inactive',
                    $metricEntity->getIsActive() ? 'label-info' : 'label-danger',
                ),
                static::COL_ACTIONS => implode(' ', $this->createActionButtons($metricEntity)),
            ];
        }

        return $rows;
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
