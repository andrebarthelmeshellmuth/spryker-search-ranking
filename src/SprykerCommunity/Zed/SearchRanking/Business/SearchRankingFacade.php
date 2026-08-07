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
use Generated\Shared\Transfer\SearchRankingScopeCopyPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigPreviewTransfer;
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
     */
    public function getMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        // This `@api` method's own signature keeps its locale parameter (search-ranking-optimizer's own
        // bridge reads real per-locale weight off the result) even though the repository's own
        // getMetricCollection() no longer needs one — composes the repository's weight-free collection
        // with its separate attachWeights() step instead of a single call.
        return $this->getRepository()->attachWeights($this->getRepository()->getMetricCollection($storeName), $storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        // Same composition as getMetricCollection() above — see that method's own comment.
        return $this->getRepository()->attachWeights($this->getRepository()->getActiveMetricCollection($storeName), $storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     */
    public function saveRelevanceWeight(string $storeName, string $localeName, float $relevanceWeight): void
    {
        $this->getFactory()->createSettingManager()->saveRelevanceWeight($storeName, $localeName, $relevanceWeight);
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     */
    public function saveRelevanceSaturationPoint(string $storeName, string $localeName, float $relevanceSaturationPoint): void
    {
        $this->getFactory()->createSettingManager()->saveRelevanceSaturationPoint($storeName, $localeName, $relevanceSaturationPoint);
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     */
    public function saveSpecificityBlendWeight(string $storeName, string $localeName, float $specificityBlendWeight): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificityBlendWeight($storeName, $localeName, $specificityBlendWeight);
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     */
    public function saveSpecificitySaturationPoint(string $storeName, string $localeName, float $specificitySaturationPoint): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificitySaturationPoint($storeName, $localeName, $specificitySaturationPoint);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getSpecificityCurveExponent(string $storeName, string $localeName): float
    {
        return $this->getFactory()->createSettingManager()->getSpecificityCurveExponent($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param float $specificityCurveExponent
     */
    public function saveSpecificityCurveExponent(string $storeName, string $localeName, float $specificityCurveExponent): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificityCurveExponent($storeName, $localeName, $specificityCurveExponent);
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     */
    public function saveSpecificityWeightExponent(string $storeName, string $localeName, float $specificityWeightExponent): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificityWeightExponent($storeName, $localeName, $specificityWeightExponent);
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     */
    public function saveSpecificityWeightShiftMagnitude(string $storeName, string $localeName, float $specificityWeightShiftMagnitude): void
    {
        $this->getFactory()->createSettingManager()->saveSpecificityWeightShiftMagnitude($storeName, $localeName, $specificityWeightShiftMagnitude);
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     * @param string $storeName
     * @param string $localeName
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): SearchRankingMetricTransfer
    {
        return $this->getFactory()->createMetricWriter()->saveMetric($metricTransfer, $storeName, $localeName);
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
     * @param string $changeSource
     */
    public function saveMetricWeight(
        int $idSearchRankingMetric,
        string $storeName,
        string $localeName,
        float $weight,
        string $changeSource = SharedSearchRankingConfig::CHANGE_SOURCE_MANUAL,
    ): void {
        $this->getFactory()->createMetricWriter()->saveMetricWeight($idSearchRankingMetric, $storeName, $localeName, $weight, $changeSource);
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
     * @return array<string>
     */
    public function resolveEffectiveWeightLocales(int $idSearchRankingMetric, string $storeName, string $localeName): array
    {
        return $this->getFactory()->createMetricWriter()->resolveEffectiveWeightLocales($idSearchRankingMetric, $storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingMetric
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
     */
    public function validateFormula(string $formula): SearchRankingFormulaValidationResponseTransfer
    {
        return $this->getFactory()->createFormulaEvaluator()->validate($formula);
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     */
    public function expandProductPageLoadTransferWithScores(ProductPageLoadTransfer $productPageLoadTransfer): ProductPageLoadTransfer
    {
        return $this->getFactory()->createScoresPageDataLoader()->expandProductPageLoadTransfer($productPageLoadTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function publishScoredProductAbstracts(): int
    {
        return $this->getFactory()->createProductAbstractScorePublisher()->publishScoredProductAbstracts();
    }

    /**
     * {@inheritDoc}
     *
     * @api
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
     */
    public function randomizeRandomMetricIfActive(?string $storeName = null, ?string $localeName = null): bool
    {
        return $this->getFactory()->createMetricRandomizer()->randomizeIfActive($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getRandomMetricName(): string
    {
        return $this->getFactory()->getConfig()->getRandomMetricName();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer
    {
        return $this->getFactory()->createCompatibilityChecker()->checkCompatibility();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isSpecificityWeightingEnabled(): bool
    {
        return $this->getFactory()->createSpecificityWeightingStatusChecker()->isEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
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
     */
    public function evaluateCurrentMetricFit(int $idSearchRankingMetric, string $storeName, string $localeName): ?float
    {
        return $this->getFactory()->createCurrentMetricFitEvaluator()->evaluate($idSearchRankingMetric, $storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param bool $confirmOverwrite
     */
    public function copyScopeConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        bool $confirmOverwrite,
    ): SearchRankingScopeCopyResultTransfer {
        return $this->getFactory()->createScopeConfigCopier()->copyScopeConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $confirmOverwrite,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function hasScopeConfiguration(string $storeName, string $localeName): bool
    {
        return $this->getFactory()->createScopeConfigCopier()->hasScopeConfiguration($storeName, $localeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     */
    public function previewScopeConfigurationCopy(string $sourceStoreName, string $sourceLocaleName): SearchRankingScopeCopyPreviewTransfer
    {
        return $this->getFactory()->createScopeConfigCopier()->previewScopeConfiguration($sourceStoreName, $sourceLocaleName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param string $mode
     * @param bool $confirmOverwrite
     */
    public function copyStoreConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        string $mode,
        bool $confirmOverwrite,
    ): SearchRankingStoreConfigCopyResultTransfer {
        return $this->getFactory()->createStoreConfigCopier()->copyStoreConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $mode,
            $confirmOverwrite,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $storeName
     */
    public function hasStoreConfiguration(string $storeName): bool
    {
        return $this->getFactory()->createStoreConfigCopier()->hasStoreConfiguration($storeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceStoreName
     */
    public function previewStoreConfigurationSync(string $sourceStoreName): SearchRankingStoreConfigPreviewTransfer
    {
        return $this->getFactory()->createStoreConfigCopier()->previewStoreConfiguration($sourceStoreName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array
    {
        return $this->getFactory()->createScopeCopyLockManager()->getActiveScopeCopyLocks();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param bool $confirmOverwrite
     */
    public function createScopeCopyLock(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        bool $confirmOverwrite,
    ): SearchRankingScopeCopyResultTransfer {
        return $this->getFactory()->createScopeCopyLockManager()->createScopeCopyLock(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $confirmOverwrite,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idSearchRankingScopeCopyLock
     */
    public function deactivateScopeCopyLock(int $idSearchRankingScopeCopyLock): void
    {
        $this->getFactory()->createScopeCopyLockManager()->deactivateScopeCopyLock($idSearchRankingScopeCopyLock);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function runScopeCopyDailySync(): int
    {
        return $this->getFactory()->createScopeCopyLockManager()->runDailySync();
    }
}
