<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence\Propel\Mapper;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric;

class SearchRankingMapper
{
    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function mapMetricEntityToTransfer(
        SpySearchRankingMetric $metricEntity,
        SearchRankingMetricTransfer $metricTransfer,
    ): SearchRankingMetricTransfer {
        return $metricTransfer
            ->setIdSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
            ->setName($metricEntity->getName())
            ->setWeight($metricEntity->getWeight())
            ->setFormula($metricEntity->getFormula())
            ->setIsActive($metricEntity->getIsActive());
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity
     *
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric
     */
    public function mapMetricTransferToEntity(
        SearchRankingMetricTransfer $metricTransfer,
        SpySearchRankingMetric $metricEntity,
    ): SpySearchRankingMetric {
        $metricEntity->setName($metricTransfer->getNameOrFail());
        $metricEntity->setWeight($metricTransfer->getWeightOrFail());
        $metricEntity->setFormula($metricTransfer->getFormulaOrFail());
        $metricEntity->setIsActive($metricTransfer->getIsActive() ?? true);

        return $metricEntity;
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric $productMetricEntity
     * @param \Generated\Shared\Transfer\SearchRankingProductMetricTransfer $productMetricTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingProductMetricTransfer
     */
    public function mapProductMetricEntityToTransfer(
        SpySearchRankingProductMetric $productMetricEntity,
        SearchRankingProductMetricTransfer $productMetricTransfer,
    ): SearchRankingProductMetricTransfer {
        return $productMetricTransfer
            ->setIdSearchRankingProductMetric($productMetricEntity->getIdSearchRankingProductMetric())
            ->setFkSearchRankingMetric($productMetricEntity->getFkSearchRankingMetric())
            ->setFkProductAbstract($productMetricEntity->getFkProductAbstract())
            ->setRawValue($productMetricEntity->getRawValue())
            ->setNormalizedValue($productMetricEntity->getNormalizedValue());
    }
}
