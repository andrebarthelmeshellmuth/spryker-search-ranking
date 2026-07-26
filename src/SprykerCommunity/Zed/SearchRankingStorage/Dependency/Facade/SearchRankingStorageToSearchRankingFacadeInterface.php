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
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(): SearchRankingMetricCollectionTransfer;

    /**
     * @return float
     */
    public function getRelevanceWeight(): float;

    /**
     * @return float
     */
    public function getRelevanceSaturationPoint(): float;

    /**
     * @return int
     */
    public function getEntropyProbeResultSize(): int;

    /**
     * @return float
     */
    public function getEntropyWeightExponent(): float;

    /**
     * @return float
     */
    public function getEntropyWeightShiftMagnitude(): float;
}
