<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric;

use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\DataSet\SearchRankingMetricDataSetInterface;

class SearchRankingMetricWriterStep implements DataImportStepInterface
{
    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @return void
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $metricEntity = SpySearchRankingMetricQuery::create()
            ->filterByName((string)$dataSet[SearchRankingMetricDataSetInterface::COL_NAME])
            ->findOneOrCreate();

        $metricEntity
            ->setWeight((float)$dataSet[SearchRankingMetricDataSetInterface::COL_WEIGHT])
            ->setFormula((string)$dataSet[SearchRankingMetricDataSetInterface::COL_FORMULA])
            ->setIsActive((bool)$dataSet[SearchRankingMetricDataSetInterface::COL_IS_ACTIVE]);

        $metricEntity->save();
    }
}
