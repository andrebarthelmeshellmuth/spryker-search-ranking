<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric;

use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;

class SearchRankingProductMetricWriterStep implements DataImportStepInterface
{
    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @return void
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $productMetricEntity = SpySearchRankingProductMetricQuery::create()
            ->filterByFkSearchRankingMetric($dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_SEARCH_RANKING_METRIC])
            ->filterByFkProductAbstract($dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT])
            ->findOneOrCreate();

        $productMetricEntity->setRawValue(
            (float)$dataSet[SearchRankingProductMetricDataSetInterface::COL_RAW_VALUE],
        );

        $productMetricEntity->save();
    }
}
