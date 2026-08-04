<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade;

use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;

interface SearchRankingStorageToSearchRankingFacadeInterface
{
    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer;

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getRelevanceWeight(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getRelevanceSaturationPoint(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getSpecificitySaturationPoint(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getSpecificityWeightExponent(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return float
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float;
}
