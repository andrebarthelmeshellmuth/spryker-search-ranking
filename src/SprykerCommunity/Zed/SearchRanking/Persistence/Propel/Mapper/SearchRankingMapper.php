<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence\Propel\Mapper;

use Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer;
use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingCalibration;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingCalibrationSearchTerm;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest;
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
            ->setIsHigherBetter($metricEntity->getIsHigherBetter());
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
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingCalibration $calibrationEntity
     * @param \Generated\Shared\Transfer\SearchRankingCalibrationTransfer $calibrationTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer
     */
    public function mapCalibrationEntityToTransfer(
        SpySearchRankingCalibration $calibrationEntity,
        SearchRankingCalibrationTransfer $calibrationTransfer,
    ): SearchRankingCalibrationTransfer {
        return $calibrationTransfer
            ->setIdSearchRankingCalibration($calibrationEntity->getIdSearchRankingCalibration())
            ->setRelevantProductCount($calibrationEntity->getRelevantProductCount())
            ->setStoreName($calibrationEntity->getStoreName())
            ->setLocaleName($calibrationEntity->getLocaleName())
            ->setStatus($calibrationEntity->getStatus())
            ->setComputedK($calibrationEntity->getComputedK())
            ->setScoreMin($calibrationEntity->getScoreMin())
            ->setScoreMax($calibrationEntity->getScoreMax())
            ->setScoreMean($calibrationEntity->getScoreMean())
            ->setScoreMedian($calibrationEntity->getScoreMedian())
            ->setScoreP25($calibrationEntity->getScoreP25())
            ->setScoreP75($calibrationEntity->getScoreP75())
            ->setSampleCount($calibrationEntity->getSampleCount())
            ->setCalculatedAt($calibrationEntity->getCalculatedAt()?->format(DATE_ATOM))
            ->setErrorMessage($calibrationEntity->getErrorMessage())
            ->setCreatedAt($calibrationEntity->getCreatedAt()?->format(DATE_ATOM));
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingCalibrationSearchTerm $searchTermEntity
     * @param \Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer $searchTermTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer
     */
    public function mapCalibrationSearchTermEntityToTransfer(
        SpySearchRankingCalibrationSearchTerm $searchTermEntity,
        SearchRankingCalibrationSearchTermTransfer $searchTermTransfer,
    ): SearchRankingCalibrationSearchTermTransfer {
        return $searchTermTransfer
            ->setIdSearchRankingCalibrationSearchTerm($searchTermEntity->getIdSearchRankingCalibrationSearchTerm())
            ->setFkSearchRankingCalibration($searchTermEntity->getFkSearchRankingCalibration())
            ->setSearchTerm($searchTermEntity->getSearchTerm())
            ->setProductsFound($searchTermEntity->getProductsFound())
            ->setScores($this->explodeScores($searchTermEntity->getScores()));
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

        return array_map('floatval', explode(',', $percentiles));
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
     * @param string|null $scores
     *
     * @return array<float>
     */
    protected function explodeScores(?string $scores): array
    {
        if ($scores === null || $scores === '') {
            return [];
        }

        return array_map('floatval', explode(',', $scores));
    }

    /**
     * @param array<float> $scores
     *
     * @return string|null
     */
    public function implodeScores(array $scores): ?string
    {
        return $scores === [] ? null : implode(',', $scores);
    }
}
