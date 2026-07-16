<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric;

use Orm\Zed\SearchRanking\Persistence\Map\SpySearchRankingMetricTableMap;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Spryker\Zed\DataImport\Business\Exception\EntityNotFoundException;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;

class MetricNameToIdSearchRankingMetricStep implements DataImportStepInterface
{
    /**
     * @var array<string, int>
     */
    protected array $idSearchRankingMetricCache = [];

    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @throws \Spryker\Zed\DataImport\Business\Exception\EntityNotFoundException
     *
     * @return void
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $metricName = (string)$dataSet[SearchRankingProductMetricDataSetInterface::COL_METRIC_NAME];

        if (!isset($this->idSearchRankingMetricCache[$metricName])) {
            /** @var int|null $idSearchRankingMetric */
            $idSearchRankingMetric = SpySearchRankingMetricQuery::create()
                ->filterByName($metricName)
                ->select([SpySearchRankingMetricTableMap::COL_ID_SEARCH_RANKING_METRIC])
                ->findOne();

            if ($idSearchRankingMetric === null) {
                throw new EntityNotFoundException(
                    sprintf('Search ranking metric with name "%s" not found. Import metrics first.', $metricName),
                );
            }

            $this->idSearchRankingMetricCache[$metricName] = (int)$idSearchRankingMetric;
        }

        $dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_SEARCH_RANKING_METRIC] = $this->idSearchRankingMetricCache[$metricName];
    }
}
