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
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
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
    public function getMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        return $this->getRepository()->getMetricCollection($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        return $this->getRepository()->getActiveMetricCollection($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getRelevanceWeight(string $storeName, string $localeName): float
    {
        return $this->getFactory()->createSettingManager()->getRelevanceWeight($storeName, $localeName);
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
    public function saveRelevanceWeight(string $storeName, string $localeName, float $relevanceWeight): void
    {
        $this->getFactory()->createSettingManager()->saveRelevanceWeight($storeName, $localeName, $relevanceWeight);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getRelevanceSaturationPoint(string $storeName, string $localeName): float
    {
        return $this->getFactory()->createSettingManager()->getRelevanceSaturationPoint($storeName, $localeName);
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
    public function saveRelevanceSaturationPoint(string $storeName, string $localeName, float $relevanceSaturationPoint): void
    {
        $this->getFactory()->createSettingManager()->saveRelevanceSaturationPoint($storeName, $localeName, $relevanceSaturationPoint);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float
    {
        return $this->getFactory()->createSettingManager()->getSpecificityBlendWeight($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $specificityBlendWeight
     *
     * @return void
     */
    public function saveSpecificityBlendWeight(string $storeName, string $localeName, float $specificityBlendWeight): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificityBlendWeight($storeName, $localeName, $specificityBlendWeight);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getSpecificitySaturationPoint(string $storeName, string $localeName): float
    {
        return $this->getFactory()->createSettingManager()->getSpecificitySaturationPoint($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $specificitySaturationPoint
     *
     * @return void
     */
    public function saveSpecificitySaturationPoint(string $storeName, string $localeName, float $specificitySaturationPoint): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificitySaturationPoint($storeName, $localeName, $specificitySaturationPoint);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getSpecificityWeightExponent(string $storeName, string $localeName): float
    {
        return $this->getFactory()->createSettingManager()->getSpecificityWeightExponent($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $specificityWeightExponent
     *
     * @return void
     */
    public function saveSpecificityWeightExponent(string $storeName, string $localeName, float $specificityWeightExponent): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificityWeightExponent($storeName, $localeName, $specificityWeightExponent);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return float
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float
    {
        return $this->getFactory()->createSettingManager()->getSpecificityWeightShiftMagnitude($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $specificityWeightShiftMagnitude
     *
     * @return void
     */
    public function saveSpecificityWeightShiftMagnitude(string $storeName, string $localeName, float $specificityWeightShiftMagnitude): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificityWeightShiftMagnitude($storeName, $localeName, $specificityWeightShiftMagnitude);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return bool
     */
    public function normalizeActiveMetricWeights(string $storeName, string $localeName): bool
    {
        return $this->getFactory()->createWeightNormalizer()->normalizeActiveWeights($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricTransfer
    {
        return $this->getRepository()->findMetricById($idSearchRankingMetric, $storeName, $localeName);
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
        return $this->getRepository()->findMetricByName(
            $name,
            SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
        );
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
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     *
     * @return void
     */
    public function saveMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName, float $weight): void
    {
        $this->getFactory()->createMetricWriter()->saveMetricWeight($idSearchRankingMetric, $storeName, $localeName, $weight);
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
    public function normalizeProductMetricValues(?string $storeName = null, ?string $localeName = null): SearchRankingNormalizationResultTransfer
    {
        return $this->getFactory()->createProductMetricNormalizer()->normalize($storeName, $localeName);
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
    public function rebuildMetricDigests(?string $storeName = null, ?string $localeName = null): int
    {
        return $this->getFactory()->createMetricDigestBuilder()->rebuildDigests($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer|null
     */
    public function findMetricDigest(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricDigestTransfer
    {
        return $this->getRepository()->findMetricDigest($idSearchRankingMetric, $storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $formula
     * @param bool $isHigherBetter
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer
     */
    public function previewFormula(
        int $idSearchRankingMetric,
        string $formula,
        bool $isHigherBetter,
        string $storeName,
        string $localeName,
    ): SearchRankingFormulaPreviewTransfer {
        return $this->getFactory()->createFormulaPreviewBuilder()->buildPreview(
            $idSearchRankingMetric,
            $formula,
            $isHigherBetter,
            $storeName,
            $localeName,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return bool
     */
    public function randomizeRandomMetricIfActive(?string $storeName = null, ?string $localeName = null): bool
    {
        return $this->getFactory()->createMetricRandomizer()->randomizeIfActive($storeName, $localeName);
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
    public function isSpecificityWeightingEnabled(): bool
    {
        return SharedSearchRankingConfig::isSpecificityWeightingEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return void
     */
    public function recordCheckOnly(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): void
    {
        $this->getFactory()->createMetricWriter()->recordCheckOnly($metricTransfer, $storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer|null
     */
    public function findLastMetricChangeHistoryEntry(int $idSearchRankingMetric): ?SearchRankingMetricHistoryTransfer
    {
        return $this->getRepository()->findLastMetricChangeHistoryEntry(
            $idSearchRankingMetric,
            SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return float|null
     */
    public function evaluateCurrentMetricFit(int $idSearchRankingMetric, string $storeName, string $localeName): ?float
    {
        return $this->getFactory()->createCurrentMetricFitEvaluator()->evaluate($idSearchRankingMetric, $storeName, $localeName);
    }
}
