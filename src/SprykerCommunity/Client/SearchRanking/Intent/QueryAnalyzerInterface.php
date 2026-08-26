<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;

/**
 * @internal Pass 1 of "Intent-Aware Alpha" — a live query-time signal detecting what KIND of query a
 * search string is (today: an exact product identifier), so query-time behavior (e.g. the hybrid-search
 * alpha blend) can react to it. NOT an Extension-namespace plugin interface — this package's own
 * {@see \SprykerCommunity\Client\SearchRanking\SearchRankingFactory::getQueryAnalyzers()} resolves the
 * active stack from a plain `SearchRankingDependencyProvider` array constant, the same "empty/default
 * array" pattern this package already uses for its other optional plugin stacks.
 *
 * Every analyzer in the stack receives the SAME transfer, built once per request by
 * {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin},
 * and runs against it in order. Analyzers MUST NOT read a signal another analyzer in the stack wrote —
 * each one only ever reads the request-scoped fields (searchString, storeName, localeName) and writes its
 * own field(s). Two independent analyzers silently coupling to each other's output is exactly the kind of
 * hidden ordering dependency this interface is designed to prevent; keep it that way for every future
 * analyzer (brand/category detection, etc.).
 */
interface QueryAnalyzerInterface
{
    /**
     * Specification:
     * - Reads `searchString`/`storeName`/`localeName` off `$queryContextTransfer` and returns a transfer
     *   with this analyzer's own signal field(s) set — every other field must be left exactly as received.
     * - Must never throw: a live catalog search must never 500 because an optional intent signal failed to
     *   resolve. Any failure (a KV read error, a malformed cached value) degrades to "no match" for this
     *   analyzer, the same graceful-degradation discipline every other best-effort signal in this package
     *   already follows (see {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin::resolveQueryVector()}).
     *
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    public function analyze(SearchRankingQueryContextTransfer $queryContextTransfer): SearchRankingQueryContextTransfer;
}
