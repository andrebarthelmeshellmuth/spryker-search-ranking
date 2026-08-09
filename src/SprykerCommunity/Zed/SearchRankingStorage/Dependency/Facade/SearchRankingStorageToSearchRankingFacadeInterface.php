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
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceWeight(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceSaturationPoint(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificitySaturationPoint(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityCurveExponent(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightExponent(string $storeName, string $localeName): float;

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float;

    /**
     * Project-wide, not store/locale scoped -- see `SearchRankingConfig::getRandomMetricName()`.
     */
    public function getRandomMetricName(): string;
}
