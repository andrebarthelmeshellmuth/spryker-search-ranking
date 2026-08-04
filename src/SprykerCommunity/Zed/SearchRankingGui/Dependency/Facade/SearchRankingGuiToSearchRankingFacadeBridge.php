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
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

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
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer
    {
        return $this->searchRankingFacade->getActiveMetricCollection($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return bool
     */
    public function normalizeActiveMetricWeights(string $storeName, string $localeName): bool
    {
        return $this->searchRankingFacade->normalizeActiveMetricWeights($storeName, $localeName);
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricTransfer
    {
        return $this->searchRankingFacade->findMetricById($idSearchRankingMetric, $storeName, $localeName);
    }

    /**
     * @param string $name
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer
    {
        return $this->searchRankingFacade->findMetricByName($name);
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer
    {
        return $this->searchRankingFacade->saveMetric($metricTransfer);
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     *
     * @return void
     */
    public function saveMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName, float $weight): void
    {
        $this->searchRankingFacade->saveMetricWeight($idSearchRankingMetric, $storeName, $localeName, $weight);
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return void
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $this->searchRankingFacade->deleteMetric($idSearchRankingMetric);
    }

    /**
     * @param string $formula
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer
     */
    public function validateFormula(string $formula): SearchRankingFormulaValidationResponseTransfer
    {
        return $this->searchRankingFacade->validateFormula($formula);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getRelevanceWeight(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getRelevanceWeight($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceWeight
     *
     * @return void
     */
    public function saveRelevanceWeight(string $storeName, string $localeName, float $relevanceWeight): void
    {
        $this->searchRankingFacade->saveRelevanceWeight($storeName, $localeName, $relevanceWeight);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getRelevanceSaturationPoint(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getRelevanceSaturationPoint($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceSaturationPoint
     *
     * @return void
     */
    public function saveRelevanceSaturationPoint(string $storeName, string $localeName, float $relevanceSaturationPoint): void
    {
        $this->searchRankingFacade->saveRelevanceSaturationPoint($storeName, $localeName, $relevanceSaturationPoint);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityBlendWeight($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityBlendWeight
     *
     * @return void
     */
    public function saveSpecificityBlendWeight(string $storeName, string $localeName, float $specificityBlendWeight): void
    {
        $this->searchRankingFacade->saveSpecificityBlendWeight($storeName, $localeName, $specificityBlendWeight);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getSpecificitySaturationPoint(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificitySaturationPoint($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificitySaturationPoint
     *
     * @return void
     */
    public function saveSpecificitySaturationPoint(string $storeName, string $localeName, float $specificitySaturationPoint): void
    {
        $this->searchRankingFacade->saveSpecificitySaturationPoint($storeName, $localeName, $specificitySaturationPoint);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getSpecificityWeightExponent(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityWeightExponent($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightExponent
     *
     * @return void
     */
    public function saveSpecificityWeightExponent(string $storeName, string $localeName, float $specificityWeightExponent): void
    {
        $this->searchRankingFacade->saveSpecificityWeightExponent($storeName, $localeName, $specificityWeightExponent);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityWeightShiftMagnitude($storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightShiftMagnitude
     *
     * @return void
     */
    public function saveSpecificityWeightShiftMagnitude(string $storeName, string $localeName, float $specificityWeightShiftMagnitude): void
    {
        $this->searchRankingFacade->saveSpecificityWeightShiftMagnitude($storeName, $localeName, $specificityWeightShiftMagnitude);
    }

    /**
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
        return $this->searchRankingFacade->previewFormula($idSearchRankingMetric, $formula, $isHigherBetter, $storeName, $localeName);
    }
}
