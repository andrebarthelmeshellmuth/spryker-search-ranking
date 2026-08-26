<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;

interface NavigationalRelevanceWeightShiftCalculatorInterface
{
    /**
     * Specification:
     * - Intent-Aware Alpha, Pass 3: composes two ADDITIVE, independently-gated shifts on top of
     *   `$baseRelevanceWeight` (whatever specificity weighting already produced, or the plain configured
     *   `relevanceWeight` when specificity weighting is disabled) — one for a detected brand, one for a
     *   detected category, each applied ONLY when its own signal is present on
     *   `$queryContextTransfer` (`detectedBrand` / `detectedCategory` non-null):
     *
     *     shift = (brandDetected ? configurationTransfer.brandMatchRelevanceWeightShift : 0.0)
     *           + (categoryDetected ? configurationTransfer.categoryMatchRelevanceWeightShift : 0.0)
     *     effectiveRelevanceWeight = clamp(baseRelevanceWeight + shift, 0.0, 1.0)
     *
     * - The clamp is applied exactly ONCE, to the final composed sum — never once per term — so two
     *   simultaneously-configured shifts can't produce a compounding-clamp artifact (each clamped to the
     *   boundary independently, then summed past it again).
     * - Both configured shift magnitudes default to `0.0` (see the transfer's own docblock), so with no
     *   project configuration this method is a pure no-op: `effectiveRelevanceWeight === $baseRelevanceWeight`
     *   for every query, detected signal or not.
     * - `categoryMatchRelevanceWeightShift`'s sign/direction is a genuinely open, UNMEASURED question —
     *   this method stays perfectly sign-agnostic about it (a positive configured value shifts toward text
     *   relevance, a negative one toward business signals, exactly like `brandMatchRelevanceWeightShift`
     *   and the specificity shift already do via their own signed magnitudes). It does not itself argue
     *   for either direction; that call is left to a future rank_eval-informed decision or to
     *   search-ranking-optimizer once this becomes a tunable parameter.
     *
     * @api
     *
     * @param float $baseRelevanceWeight
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    public function calculateEffectiveRelevanceWeight(
        float $baseRelevanceWeight,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        SearchRankingQueryContextTransfer $queryContextTransfer,
    ): float;
}
