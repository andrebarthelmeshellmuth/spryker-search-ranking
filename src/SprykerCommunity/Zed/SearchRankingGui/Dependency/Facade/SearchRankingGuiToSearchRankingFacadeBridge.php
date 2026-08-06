<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade;

use Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigCopyResultTransfer;

class SearchRankingGuiToSearchRankingFacadeBridge implements SearchRankingGuiToSearchRankingFacadeInterface
{
    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface
     */
    protected $searchRankingFacade;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface $searchRankingFacade
     */
    public function __construct($searchRankingFacade)
    {
        $this->searchRankingFacade = $searchRankingFacade;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        return $this->searchRankingFacade->getActiveMetricCollection($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function normalizeActiveMetricWeights(string $storeName, string $localeName): bool
    {
        return $this->searchRankingFacade->normalizeActiveMetricWeights($storeName, $localeName);
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricById(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricTransfer
    {
        return $this->searchRankingFacade->findMetricById($idSearchRankingMetric, $storeName, $localeName);
    }

    /**
     * @param string $name
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer
    {
        return $this->searchRankingFacade->findMetricByName($name);
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): SearchRankingMetricTransfer
    {
        return $this->searchRankingFacade->saveMetric($metricTransfer, $storeName, $localeName);
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     */
    public function saveMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName, float $weight): void
    {
        $this->searchRankingFacade->saveMetricWeight($idSearchRankingMetric, $storeName, $localeName, $weight);
    }

    /**
     * @param int $idSearchRankingMetric
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $this->searchRankingFacade->deleteMetric($idSearchRankingMetric);
    }

    /**
     * @param string $formula
     */
    public function validateFormula(string $formula): SearchRankingFormulaValidationResponseTransfer
    {
        return $this->searchRankingFacade->validateFormula($formula);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceWeight(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getRelevanceWeight($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceWeight
     */
    public function saveRelevanceWeight(string $storeName, string $localeName, float $relevanceWeight): void
    {
        $this->searchRankingFacade->saveRelevanceWeight($storeName, $localeName, $relevanceWeight);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceSaturationPoint(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getRelevanceSaturationPoint($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceSaturationPoint
     */
    public function saveRelevanceSaturationPoint(string $storeName, string $localeName, float $relevanceSaturationPoint): void
    {
        $this->searchRankingFacade->saveRelevanceSaturationPoint($storeName, $localeName, $relevanceSaturationPoint);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityBlendWeight($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityBlendWeight
     */
    public function saveSpecificityBlendWeight(string $storeName, string $localeName, float $specificityBlendWeight): void
    {
        $this->searchRankingFacade->saveSpecificityBlendWeight($storeName, $localeName, $specificityBlendWeight);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificitySaturationPoint(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificitySaturationPoint($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificitySaturationPoint
     */
    public function saveSpecificitySaturationPoint(string $storeName, string $localeName, float $specificitySaturationPoint): void
    {
        $this->searchRankingFacade->saveSpecificitySaturationPoint($storeName, $localeName, $specificitySaturationPoint);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightExponent(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityWeightExponent($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightExponent
     */
    public function saveSpecificityWeightExponent(string $storeName, string $localeName, float $specificityWeightExponent): void
    {
        $this->searchRankingFacade->saveSpecificityWeightExponent($storeName, $localeName, $specificityWeightExponent);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityWeightShiftMagnitude($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightShiftMagnitude
     */
    public function saveSpecificityWeightShiftMagnitude(string $storeName, string $localeName, float $specificityWeightShiftMagnitude): void
    {
        $this->searchRankingFacade->saveSpecificityWeightShiftMagnitude($storeName, $localeName, $specificityWeightShiftMagnitude);
    }

    public function isSpecificityWeightingEnabled(): bool
    {
        return $this->searchRankingFacade->isSpecificityWeightingEnabled();
    }

    /**
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
        return $this->searchRankingFacade->previewFormula($idSearchRankingMetric, $formula, $isHigherBetter, $storeName, $localeName);
    }

    /**
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
        return $this->searchRankingFacade->copyScopeConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $confirmOverwrite,
        );
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function hasScopeConfiguration(string $storeName, string $localeName): bool
    {
        return $this->searchRankingFacade->hasScopeConfiguration($storeName, $localeName);
    }

    /**
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
        return $this->searchRankingFacade->copyStoreConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $mode,
            $confirmOverwrite,
        );
    }

    /**
     * @param string $storeName
     */
    public function hasStoreConfiguration(string $storeName): bool
    {
        return $this->searchRankingFacade->hasStoreConfiguration($storeName);
    }

    /**
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array
    {
        return $this->searchRankingFacade->getActiveScopeCopyLocks();
    }

    /**
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
        return $this->searchRankingFacade->createScopeCopyLock(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $confirmOverwrite,
        );
    }

    /**
     * @param int $idSearchRankingScopeCopyLock
     */
    public function deactivateScopeCopyLock(int $idSearchRankingScopeCopyLock): void
    {
        $this->searchRankingFacade->deactivateScopeCopyLock($idSearchRankingScopeCopyLock);
    }
}
