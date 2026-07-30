<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Elastica\Query\AbstractQuery;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

interface EntropyWeightCalculatorInterface
{
    /**
     * Specification:
     * - Fires ONE additional, lightweight Elasticsearch query (scores only, no `_source`) for the top
     *   `$configurationTransfer->getEntropyProbeResultSize()` candidates of the given (not yet
     *   function_score-wrapped) base query, and derives a per-query `relevanceWeight` from the shape of
     *   their raw text-relevance scores.
     * - The configured `relevanceWeight` is treated as a BASELINE, not fully replaced: a flat score
     *   distribution (business signals should matter more) shifts it down, toward 0, by up to
     *   `$configurationTransfer->getEntropyWeightShiftMagnitude()`; a single dominant score (text
     *   relevance already discriminates well) shifts it up, toward 1, by the same maximum amount. A
     *   perfectly ambiguous distribution (normalized entropy exactly 0.5) leaves the baseline unchanged.
     *   `getEntropyWeightExponent()` reshapes how sharply the shift ramps up as the distribution moves
     *   away from that neutral point, without moving the neutral point itself.
     * - Falls back to the configured `relevanceWeight` UNCHANGED whenever the probe can't produce a
     *   usable signal: zero hits, or the probe query itself fails (network hiccup, engine hiccup) — this
     *   method never throws, and never blocks or breaks the real search it's called from.
     *
     * @api
     *
     * @param \Elastica\Query\AbstractQuery $baseQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return float
     */
    public function calculateRelevanceWeight(
        AbstractQuery $baseQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): float;

    /**
     * Specification:
     * - Same computation as {@see calculateRelevanceWeight()}, returned as a full
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult} instead of just the
     *   resulting weight — so a caller that needs to explain the shift (e.g. the search-debug overlay)
     *   doesn't have to re-derive the probe/entropy/shift arithmetic itself.
     *
     * @api
     *
     * @param \Elastica\Query\AbstractQuery $baseQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult
     */
    public function calculateWeightingResult(
        AbstractQuery $baseQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): EntropyWeightingResult;
}
