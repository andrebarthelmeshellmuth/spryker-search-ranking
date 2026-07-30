<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence\Propel\Mapper;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory;
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
            ->setIsActive($metricEntity->getIsActive())
            ->setIsHigherBetter($metricEntity->getIsHigherBetter())
            ->setShape($metricEntity->getShape());
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
        $metricEntity->setIsHigherBetter($metricTransfer->getIsHigherBetter() ?? true);
        $metricEntity->setShape($metricTransfer->getShape());

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

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest $digestEntity
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer
     */
    public function mapMetricDigestEntityToTransfer(
        SpySearchRankingMetricDigest $digestEntity,
        SearchRankingMetricDigestTransfer $digestTransfer,
    ): SearchRankingMetricDigestTransfer {
        return $digestTransfer
            ->setIdSearchRankingMetricDigest($digestEntity->getIdSearchRankingMetricDigest())
            ->setFkSearchRankingMetric($digestEntity->getFkSearchRankingMetric())
            ->setMinValue($digestEntity->getMinValue())
            ->setMaxValue($digestEntity->getMaxValue())
            ->setMeanValue($digestEntity->getMeanValue())
            ->setMedianValue($digestEntity->getMedianValue())
            ->setSampleCount($digestEntity->getSampleCount())
            ->setPercentiles($this->explodePercentiles($digestEntity->getPercentiles()));
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest $digestEntity
     *
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest
     */
    public function mapMetricDigestTransferToEntity(
        SearchRankingMetricDigestTransfer $digestTransfer,
        SpySearchRankingMetricDigest $digestEntity,
    ): SpySearchRankingMetricDigest {
        $digestEntity->setFkSearchRankingMetric($digestTransfer->getFkSearchRankingMetricOrFail());
        $digestEntity->setMinValue($digestTransfer->getMinValueOrFail());
        $digestEntity->setMaxValue($digestTransfer->getMaxValueOrFail());
        $digestEntity->setMeanValue($digestTransfer->getMeanValueOrFail());
        $digestEntity->setMedianValue($digestTransfer->getMedianValueOrFail());
        $digestEntity->setSampleCount($digestTransfer->getSampleCountOrFail());
        $digestEntity->setPercentiles($this->implodePercentiles($digestTransfer->getPercentiles()));

        return $digestEntity;
    }

    /**
     * @param string|null $percentiles
     *
     * @return array<float>
     */
    protected function explodePercentiles(?string $percentiles): array
    {
        if ($percentiles === null || $percentiles === '') {
            return [];
        }

        return array_map(static fn ($value): float => (float)$value, explode(',', $percentiles));
    }

    /**
     * @param array<float> $percentiles
     *
     * @return string
     */
    protected function implodePercentiles(array $percentiles): string
    {
        return implode(',', $percentiles);
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory $historyEntity
     * @param \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer $historyTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer
     */
    public function mapMetricHistoryEntityToTransfer(
        SpySearchRankingMetricHistory $historyEntity,
        SearchRankingMetricHistoryTransfer $historyTransfer,
    ): SearchRankingMetricHistoryTransfer {
        return $historyTransfer
            ->setIdSearchRankingMetricHistory($historyEntity->getIdSearchRankingMetricHistory())
            ->setFkSearchRankingMetric($historyEntity->getFkSearchRankingMetric())
            ->setMetricName($historyEntity->getMetricName())
            ->setWeight($historyEntity->getWeight())
            ->setFormula($historyEntity->getFormula())
            ->setIsActive($historyEntity->getIsActive())
            ->setIsHigherBetter($historyEntity->getIsHigherBetter())
            ->setMinValue($historyEntity->getMinValue())
            ->setMaxValue($historyEntity->getMaxValue())
            ->setMeanValue($historyEntity->getMeanValue())
            ->setMedianValue($historyEntity->getMedianValue())
            ->setSampleCount($historyEntity->getSampleCount())
            ->setPercentiles($this->explodePercentiles($historyEntity->getPercentiles()))
            ->setFitRSquared($historyEntity->getFitRSquared())
            ->setIsChange($historyEntity->getIsChange())
            ->setCreatedAt($historyEntity->getCreatedAt()?->format(DATE_ATOM));
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer $historyTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory $historyEntity
     *
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory
     */
    public function mapMetricHistoryTransferToEntity(
        SearchRankingMetricHistoryTransfer $historyTransfer,
        SpySearchRankingMetricHistory $historyEntity,
    ): SpySearchRankingMetricHistory {
        $historyEntity->setFkSearchRankingMetric($historyTransfer->getFkSearchRankingMetricOrFail());
        $historyEntity->setMetricName($historyTransfer->getMetricNameOrFail());
        $historyEntity->setWeight($historyTransfer->getWeightOrFail());
        $historyEntity->setFormula($historyTransfer->getFormulaOrFail());
        $historyEntity->setIsActive($historyTransfer->getIsActiveOrFail());
        $historyEntity->setIsHigherBetter($historyTransfer->getIsHigherBetterOrFail());
        $historyEntity->setMinValue($historyTransfer->getMinValue());
        $historyEntity->setMaxValue($historyTransfer->getMaxValue());
        $historyEntity->setMeanValue($historyTransfer->getMeanValue());
        $historyEntity->setMedianValue($historyTransfer->getMedianValue());
        $historyEntity->setSampleCount($historyTransfer->getSampleCount());
        $historyEntity->setPercentiles(
            $historyTransfer->getPercentiles() === [] ? null : $this->implodePercentiles($historyTransfer->getPercentiles()),
        );
        $historyEntity->setFitRSquared($historyTransfer->getFitRSquared());
        $historyEntity->setIsChange($historyTransfer->getIsChange() ?? true);

        return $historyEntity;
    }
}
