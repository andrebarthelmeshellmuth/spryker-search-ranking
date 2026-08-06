<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade;

use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;

class SearchRankingStorageToSearchRankingFacadeBridge implements SearchRankingStorageToSearchRankingFacadeInterface
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
    public function getRelevanceWeight(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getRelevanceWeight($storeName, $localeName);
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
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityBlendWeight($storeName, $localeName);
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
     */
    public function getSpecificityCurveExponent(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityCurveExponent($storeName, $localeName);
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
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float
    {
        return $this->searchRankingFacade->getSpecificityWeightShiftMagnitude($storeName, $localeName);
    }
}
