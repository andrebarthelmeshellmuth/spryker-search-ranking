<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business;

use Generated\Shared\Transfer\ProductPageLoadTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;
use Spryker\Zed\Kernel\Business\AbstractFacade;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingBusinessFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface getRepository()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface getEntityManager()
 */
class SearchRankingFacade extends AbstractFacade implements SearchRankingFacadeInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getMetricCollection(): SearchRankingMetricCollectionTransfer
    {
        return $this->getRepository()->getMetricCollection();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(): SearchRankingMetricCollectionTransfer
    {
        return $this->getRepository()->getActiveMetricCollection();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getRelevanceWeight(): float
    {
        return $this->getFactory()->createSettingManager()->getRelevanceWeight();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $relevanceWeight
     *
     * @return void
     */
    public function saveRelevanceWeight(float $relevanceWeight): void
    {
        $this->getFactory()->createSettingManager()->saveRelevanceWeight($relevanceWeight);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getRelevanceSaturationPoint(): float
    {
        return $this->getFactory()->createSettingManager()->getRelevanceSaturationPoint();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $relevanceSaturationPoint
     *
     * @return void
     */
    public function saveRelevanceSaturationPoint(float $relevanceSaturationPoint): void
    {
        $this->getFactory()->createSettingManager()->saveRelevanceSaturationPoint($relevanceSaturationPoint);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return int
     */
    public function getEntropyProbeResultSize(): int
    {
        return $this->getFactory()->createSettingManager()->getEntropyProbeResultSize();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $entropyProbeResultSize
     *
     * @return void
     */
    public function saveEntropyProbeResultSize(int $entropyProbeResultSize): void
    {
        $this->getFactory()->createSettingManager()->saveEntropyProbeResultSize($entropyProbeResultSize);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getEntropyWeightExponent(): float
    {
        return $this->getFactory()->createSettingManager()->getEntropyWeightExponent();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $entropyWeightExponent
     *
     * @return void
     */
    public function saveEntropyWeightExponent(float $entropyWeightExponent): void
    {
        $this->getFactory()->createSettingManager()->saveEntropyWeightExponent($entropyWeightExponent);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getEntropyWeightShiftMagnitude(): float
    {
        return $this->getFactory()->createSettingManager()->getEntropyWeightShiftMagnitude();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $entropyWeightShiftMagnitude
     *
     * @return void
     */
    public function saveEntropyWeightShiftMagnitude(float $entropyWeightShiftMagnitude): void
    {
        $this->getFactory()->createSettingManager()->saveEntropyWeightShiftMagnitude($entropyWeightShiftMagnitude);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return bool
     */
    public function normalizeActiveMetricWeights(): bool
    {
        return $this->getFactory()->createWeightNormalizer()->normalizeActiveWeights();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric): ?SearchRankingMetricTransfer
    {
        return $this->getRepository()->findMetricById($idSearchRankingMetric);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $name
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer
    {
        return $this->getRepository()->findMetricByName($name);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer
    {
        return $this->getFactory()->createMetricWriter()->saveMetric($metricTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return void
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $this->getFactory()->createMetricWriter()->deleteMetric($idSearchRankingMetric);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $formula
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer
     */
    public function validateFormula(string $formula): SearchRankingFormulaValidationResponseTransfer
    {
        return $this->getFactory()->createFormulaEvaluator()->validate($formula);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer
     */
    public function normalizeProductMetricValues(): SearchRankingNormalizationResultTransfer
    {
        return $this->getFactory()->createProductMetricNormalizer()->normalize();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ProductPageLoadTransfer $productPageLoadTransfer
     *
     * @return \Generated\Shared\Transfer\ProductPageLoadTransfer
     */
    public function expandProductPageLoadTransferWithScores(ProductPageLoadTransfer $productPageLoadTransfer): ProductPageLoadTransfer
    {
        return $this->getFactory()->createScoresPageDataLoader()->expandProductPageLoadTransfer($productPageLoadTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return int
     */
    public function publishScoredProductAbstracts(): int
    {
        return $this->getFactory()->createProductAbstractScorePublisher()->publishScoredProductAbstracts();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return int
     */
    public function rebuildMetricDigests(): int
    {
        return $this->getFactory()->createMetricDigestBuilder()->rebuildDigests();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer|null
     */
    public function findMetricDigest(int $idSearchRankingMetric): ?SearchRankingMetricDigestTransfer
    {
        return $this->getRepository()->findMetricDigest($idSearchRankingMetric);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $formula
     * @param bool $isHigherBetter
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer
     */
    public function previewFormula(int $idSearchRankingMetric, string $formula, bool $isHigherBetter): SearchRankingFormulaPreviewTransfer
    {
        return $this->getFactory()->createFormulaPreviewBuilder()->buildPreview($idSearchRankingMetric, $formula, $isHigherBetter);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return bool
     */
    public function randomizeRandomMetricIfActive(): bool
    {
        return $this->getFactory()->createMetricRandomizer()->randomizeIfActive();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer
    {
        return $this->getFactory()->createCompatibilityChecker()->checkCompatibility();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return bool
     */
    public function isEntropyWeightingEnabled(): bool
    {
        return SharedSearchRankingConfig::isEntropyWeightingEnabled();
    }
}
