<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Elastica\Query\AbstractQuery;

interface EntropyWeightCalculatorInterface
{
    /**
     * Specification:
     * - Fires ONE additional, lightweight Elasticsearch query (scores only, no `_source`) for the top
     *   {@see \SprykerCommunity\Client\SearchRanking\SearchRankingConfig::getEntropyProbeResultSize()}
     *   candidates of the given (not yet function_score-wrapped) base query, and derives a per-query
     *   `relevanceWeight` from the shape of their raw text-relevance scores: a single dominant score
     *   returns a weight close to `$configuredRelevanceWeight`'s own text-relevance-heavy end, a flat
     *   score distribution returns a weight pulled toward the business-signal-heavy end.
     * - Falls back to `$configuredRelevanceWeight` UNCHANGED whenever the probe can't produce a usable
     *   signal: zero hits, or the probe query itself fails (network hiccup, engine hiccup) — this method
     *   never throws, and never blocks or breaks the real search it's called from.
     *
     * @api
     *
     * @param \Elastica\Query\AbstractQuery $baseQuery
     * @param float $configuredRelevanceWeight
     *
     * @return float
     */
    public function calculateRelevanceWeight(AbstractQuery $baseQuery, float $configuredRelevanceWeight): float;
}
