<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

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
     *
     * @return \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer
     */
    public function checkCompatibility(): SearchRankingEngineCompatibilityTransfer;
}
