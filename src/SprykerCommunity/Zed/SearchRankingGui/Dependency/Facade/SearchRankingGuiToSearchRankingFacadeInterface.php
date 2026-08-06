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

interface SearchRankingGuiToSearchRankingFacadeInterface
{
    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function normalizeActiveMetricWeights(string $storeName, string $localeName): bool;

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricById(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricTransfer;

    /**
     * @param string $name
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer;

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): SearchRankingMetricTransfer;

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     */
    public function saveMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName, float $weight): void;

    /**
     * @param int $idSearchRankingMetric
     */
    public function deleteMetric(int $idSearchRankingMetric): void;

    /**
     * @param string $formula
     */
    public function validateFormula(string $formula): SearchRankingFormulaValidationResponseTransfer;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceWeight(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceWeight
     */
    public function saveRelevanceWeight(string $storeName, string $localeName, float $relevanceWeight): void;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceSaturationPoint(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceSaturationPoint
     */
    public function saveRelevanceSaturationPoint(string $storeName, string $localeName, float $relevanceSaturationPoint): void;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityBlendWeight
     */
    public function saveSpecificityBlendWeight(string $storeName, string $localeName, float $specificityBlendWeight): void;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificitySaturationPoint(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificitySaturationPoint
     */
    public function saveSpecificitySaturationPoint(string $storeName, string $localeName, float $specificitySaturationPoint): void;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightExponent(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightExponent
     */
    public function saveSpecificityWeightExponent(string $storeName, string $localeName, float $specificityWeightExponent): void;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightShiftMagnitude
     */
    public function saveSpecificityWeightShiftMagnitude(string $storeName, string $localeName, float $specificityWeightShiftMagnitude): void;

    /**
     * Whether specificity-aware relevance weighting is active — a pure code-level project flag, not
     * Zed-editable. See {@see \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface::isSpecificityWeightingEnabled()}.
     */
    public function isSpecificityWeightingEnabled(): bool;

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
    ): SearchRankingFormulaPreviewTransfer;

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
    ): SearchRankingScopeCopyResultTransfer;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function hasScopeConfiguration(string $storeName, string $localeName): bool;

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
    ): SearchRankingStoreConfigCopyResultTransfer;

    /**
     * @param string $storeName
     */
    public function hasStoreConfiguration(string $storeName): bool;

    /**
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array;

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
    ): SearchRankingScopeCopyResultTransfer;

    /**
     * @param int $idSearchRankingScopeCopyLock
     */
    public function deactivateScopeCopyLock(int $idSearchRankingScopeCopyLock): void;
}
