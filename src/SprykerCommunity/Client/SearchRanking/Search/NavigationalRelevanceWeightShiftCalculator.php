<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;

class NavigationalRelevanceWeightShiftCalculator implements NavigationalRelevanceWeightShiftCalculatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param float $baseRelevanceWeight
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    public function calculateEffectiveRelevanceWeight(
        float $baseRelevanceWeight,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        SearchRankingQueryContextTransfer $queryContextTransfer,
    ): float {
        $shift = 0.0;

        if ($queryContextTransfer->getDetectedBrand() !== null) {
            $shift += (float)$configurationTransfer->getBrandMatchRelevanceWeightShift();
        }

        if ($queryContextTransfer->getDetectedCategory() !== null) {
            $shift += (float)$configurationTransfer->getCategoryMatchRelevanceWeightShift();
        }

        return max(0.0, min(1.0, $baseRelevanceWeight + $shift));
    }
}
