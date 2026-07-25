<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade;

use Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;

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
     * @return bool
     */
    public function normalizeActiveMetricWeights(): bool
    {
        return $this->searchRankingFacade->normalizeActiveMetricWeights();
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric): ?SearchRankingMetricTransfer
    {
        return $this->searchRankingFacade->findMetricById($idSearchRankingMetric);
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
     * @return float
     */
    public function getRelevanceWeight(): float
    {
        return $this->searchRankingFacade->getRelevanceWeight();
    }

    /**
     * @param float $relevanceWeight
     *
     * @return void
     */
    public function saveRelevanceWeight(float $relevanceWeight): void
    {
        $this->searchRankingFacade->saveRelevanceWeight($relevanceWeight);
    }

    /**
     * @return float
     */
    public function getRelevanceSaturationPoint(): float
    {
        return $this->searchRankingFacade->getRelevanceSaturationPoint();
    }

    /**
     * @param float $relevanceSaturationPoint
     *
     * @return void
     */
    public function saveRelevanceSaturationPoint(float $relevanceSaturationPoint): void
    {
        $this->searchRankingFacade->saveRelevanceSaturationPoint($relevanceSaturationPoint);
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $formula
     * @param bool $isHigherBetter
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer
     */
    public function previewFormula(int $idSearchRankingMetric, string $formula, bool $isHigherBetter): SearchRankingFormulaPreviewTransfer
    {
        return $this->searchRankingFacade->previewFormula($idSearchRankingMetric, $formula, $isHigherBetter);
    }
}
