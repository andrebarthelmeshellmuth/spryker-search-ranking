<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\RandomImpact;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

interface RandomImpactCalculatorInterface
{
    /**
     * Whether this (store, locale)'s live configuration has an active random tie-breaker metric with a
     * non-zero weight — the gate that decides whether the random-impact checkbox appears on the search
     * results page at all. False whenever `randomMetricName` is unset (a payload published before this
     * feature existed) or that metric isn't in `metricWeights` (not currently active) or its weight is 0
     * (configured but contributing nothing).
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     */
    public function isActive(SearchRankingConfigurationStorageTransfer $configurationTransfer): bool;

    /**
     * Simulates removing the random tie-breaker metric's own contribution from each hit's live blended
     * score, then re-sorts to find each product's simulated position within THIS SAME set of hits (a
     * same-page comparison, not a full-corpus re-query — see this package's README for why). Because the
     * live formula is a plain weighted sum, this needs no knowledge of the OTHER metrics' signals or
     * weights at all — subtracting exactly the random metric's own weighted contribution from a hit's
     * already-final score is equivalent to recomputing the whole formula with that one weight forced to 0.
     *
     * @param array<int, array{idProductAbstract: int, score: float, randomSignal: float}> $hits Every hit
     *   on the current page, in their LIVE display order (index 0 = the top result) — `score` is the
     *   hit's own final blended ranking score (Elasticsearch's `_score`, already the function_score
     *   result, not raw text relevance), `randomSignal` is that hit's own raw, normalized value for the
     *   random metric (0.0 when the product's own `scores.<randomMetricName>` field is absent).
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return array<int, int> idProductAbstract => position delta (simulatedPosition - livePosition).
     *   Negative means the product would rank BETTER (closer to position 1) without random's influence —
     *   i.e. random is currently holding it back. Positive means it would rank WORSE — random is
     *   currently helping it. A product whose position wouldn't change is omitted entirely, not included
     *   with a delta of 0.
     */
    public function calculate(array $hits, SearchRankingConfigurationStorageTransfer $configurationTransfer): array;
}
