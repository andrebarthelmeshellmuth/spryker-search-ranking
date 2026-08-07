<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;

interface SpecificityWeightCalculatorInterface
{
    /**
     * Specification:
     * - Fires ONE additional, lightweight `_termvectors` probe (no real catalog query at all — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface}) for
     *   `$searchString`, and derives a per-query `relevanceWeight` from how SPECIFIC that string's own
     *   terms are against the corpus (rare terms like a SKU → highly specific; only common words like
     *   "office chair" → not specific) rather than from the shape of any candidate's `_score`.
     * - The configured `relevanceWeight` is treated as a BASELINE, not fully replaced: a highly specific
     *   query (text relevance already discriminates well) shifts it up, toward 1, by up to
     *   `$configurationTransfer->getSpecificityWeightShiftMagnitude()`; an unspecific/browsy query shifts
     *   it down, toward 0, by the same maximum amount. A perfectly average-specificity query (normalized
     *   specificity exactly 0.5, i.e. at the calibrated saturation point) leaves the baseline unchanged.
     *   `getSpecificityWeightExponent()` reshapes how sharply the shift ramps up as specificity moves away
     *   from that neutral point, without moving the neutral point itself.
     * - Falls back to the configured `relevanceWeight` UNCHANGED whenever the probe can't produce a usable
     *   signal: an empty search string, no query term carrying any real corpus evidence, or the probe
     *   itself fails (network hiccup, engine hiccup) — this method never throws, and never blocks or
     *   breaks the real search it's called from.
     *
     * @api
     *
     * @param string $searchString
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     */
    public function calculateRelevanceWeight(
        string $searchString,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): float;

    /**
     * Specification:
     * - Same computation as {@see calculateRelevanceWeight()}, returned as a full
     *   {@see \Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer} instead of just
     *   the resulting weight — so a caller that needs to explain the shift (e.g. the search-debug overlay)
     *   doesn't have to re-derive the probe/specificity/shift arithmetic itself.
     *
     * @api
     *
     * @param string $searchString
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     */
    public function calculateWeightingResult(
        string $searchString,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): SearchRankingSpecificityWeightingResultTransfer;
}
