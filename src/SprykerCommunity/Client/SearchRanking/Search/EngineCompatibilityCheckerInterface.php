<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Generated\Shared\Transfer\SearchRankingEngineCapabilityTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;

interface EngineCompatibilityCheckerInterface
{
    /**
     * Specification:
     * - Reports the live engine's self-identified distribution and version (informational only).
     * - Probes a fixed set of search-engine constructs this package uses today, or could use in a future
     *   phase (see the search-ranking roadmap), by firing each construct at the engine directly and
     *   reading back whether it was accepted — never by comparing a version number, since OpenSearch and
     *   Elasticsearch report incompatible version identifiers under the same API surface.
     * - Every probe runs cluster-wide (no store/index resolution needed): capability support is a
     *   property of the engine, not of any one store's index.
     * - Includes the same {@see probeCompletionSuggesterCapability()} result as one of its capabilities
     *   (so it shows up on this package's own Zed compatibility page too).
     */
    public function checkCompatibility(): SearchRankingEngineCompatibilityTransfer;

    /**
     * Specification:
     * - Live probe of whether the engine supports the `completion` suggester field type — Pass 2 of
     *   "Intent-Aware Alpha"'s {@see \SprykerCommunity\Client\SearchRanking\Intent\SuggestIndexEntityLookup}
     *   depends on it. Unlike a query-DSL construct, a suggester's support cannot be probed via
     *   `_validate/query` (that endpoint only validates the `query` clause, not `suggest`) — this probe
     *   instead PUTs a throwaway index with a `completion`-typed field, reads whether the engine accepted
     *   the mapping, then deletes the throwaway index either way (best-effort cleanup on both the success
     *   and failure path).
     * - Never throws: any failure (including the cleanup call itself) is caught and reflected as
     *   `isSupported: false`, never propagated — see {@see \SprykerCommunity\Client\SearchRanking\Intent\EntityLookupTierResolver},
     *   the caller that turns this into a graceful runtime degradation.
     * - Split out from {@see checkCompatibility()} (rather than only living inside its aggregate result) so
     *   the tier-selection path can call this ONE cheap probe without also firing the other four unrelated
     *   ones on every resolution.
     */
    public function probeCompletionSuggesterCapability(): SearchRankingEngineCapabilityTransfer;
}
