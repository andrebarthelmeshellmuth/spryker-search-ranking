<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

interface QueryTermFrequencyFetcherInterface
{
    /**
     * Specification:
     * - Learns, per DISTINCT query term, how many documents in the corpus actually contain it (`doc_freq`)
     *   plus the corpus-wide document count (`docCount`) — WITHOUT running the real search query at all.
     * - Standalone convenience wrapper around {@see registerProbes()}/{@see consumeProbes()}: builds its
     *   own private {@see MsearchProbeBatcherInterface}, registers every probe this call needs, fires
     *   exactly ONE `_msearch` via that batcher's `execute()`, and parses the result. A caller that wants
     *   this probe to ride in a LARGER shared `_msearch` batch alongside other collaborators' own probes
     *   (e.g. entity-lookup exists-checks) should call {@see registerProbes()}/{@see consumeProbes()}
     *   directly against a shared batcher instead of this method — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   for that orchestration.
     * - Implemented as N `size:0` `match` count-sub-queries (one per distinct term × probed field, plus one
     *   `match_all` count for `docCount`) — NOT `_termvectors`: `_termvectors` cannot ride in an `_msearch`
     *   at all (a fundamentally different endpoint), which is exactly why this shape was chosen. See this
     *   package's README/CHANGELOG for the full round-trip-cost rationale.
     * - `$fieldToSearchAnalyzer` maps each field to probe (e.g. `full-text-boosted`) to the SEARCH-time
     *   analyzer name to pass on each `match` sub-query — required so the count reflects how the real query
     *   itself gets analyzed, not a field's INDEX-time analyzer (which can tokenize very differently, e.g.
     *   an edge-ngram index analyzer exploding one search token into several index-time sub-tokens).
     * - Distinct terms are extracted via a simple whitespace split of `$searchString` (mirroring
     *   {@see \SprykerCommunity\Shared\SearchRanking\Intent\QueryWindowExtractor}'s own simple-tokenization
     *   convention), NOT server-side analysis — a real, KNOWN semantic difference from the old
     *   `_termvectors` shape, which had ES analyze `$searchString` itself and report per-ANALYZED-TOKEN
     *   frequencies (e.g. lowercased). A raw whitespace-split term still matches correctly (the `match`
     *   sub-query's own `analyzer` parameter still normalizes it search-side), but the MAP KEY reported by
     *   {@see QueryTermFrequencyResult::getTermDocumentFrequencies()} is the raw, un-analyzed term now,
     *   not ES's own analyzed token — see this package's README for why this is an accepted trade-off (a
     *   heavier `_analyze` round trip to recover exact server-side tokens would itself work against the
     *   whole point of this rewrite).
     * - Probes every given field and combines each term's `doc_freq` via `max()` across fields — some
     *   catalogs (e.g. SKU-derived content) only populate certain fields, so a single-field probe can
     *   silently under-report.
     * - `docCount` is a single `match_all` `size:0` count across the WHOLE index — a KNOWN semantic shift
     *   from the old `_termvectors` shape's per-field `field_statistics.doc_count` (documents that actually
     *   populate that specific field), which this rewrite approximates as "documents in the index at all".
     *   Identical in practice whenever every document populates every probed field (true for this project's
     *   own `page` index), diverges only for a catalog with fields that are sparsely populated.
     * - A term missing/zero in the response is a genuine `0`, not an error — resolved to `0` in the
     *   returned result rather than surfaced as an exception. A total corpus-size failure (docCount
     *   resolves to `0`, e.g. the target index doesn't exist) short-circuits to an entirely EMPTY term map
     *   rather than a map of all-zero entries — nothing meaningful can be measured about term rarity
     *   without a real corpus size to divide by.
     * - Never throws: an empty search string, an empty `$fieldToSearchAnalyzer`, or any engine/network
     *   failure all resolve to a result with `docCount = 0` and no term entries, letting the caller fall
     *   back gracefully.
     *
     * @api
     *
     * @param string $searchString
     * @param array<string, string> $fieldToSearchAnalyzer
     */
    public function fetch(string $searchString, array $fieldToSearchAnalyzer): QueryTermFrequencyResult;

    /**
     * Specification:
     * - Queues every probe {@see fetch()} would need onto `$batcher`, namespaced under `$probeKeyPrefix` so
     *   this call can share the batcher with other collaborators' own probes without key collisions. Fires
     *   nothing itself — the caller is responsible for calling `$batcher->execute()` once every
     *   collaborator has finished registering.
     * - A no-op (registers nothing) for an empty `$searchString` or an empty `$fieldToSearchAnalyzer`,
     *   mirroring {@see fetch()}'s own early-return contract.
     * - Never throws.
     *
     * @api
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param string $probeKeyPrefix
     * @param string $searchString
     * @param array<string, string> $fieldToSearchAnalyzer
     */
    public function registerProbes(
        MsearchProbeBatcherInterface $batcher,
        string $probeKeyPrefix,
        string $searchString,
        array $fieldToSearchAnalyzer,
    ): void;

    /**
     * Specification:
     * - Reads back every probe {@see registerProbes()} queued under the SAME `$probeKeyPrefix`/
     *   `$searchString`/`$fieldToSearchAnalyzer` from `$batcher` (which must already have had `execute()`
     *   called on it) and parses them into the same result shape {@see fetch()} returns.
     * - Never throws: a probe missing from the batcher (never registered, or the whole batch failed at the
     *   transport level) resolves the same way a failed standalone probe does in {@see fetch()}.
     *
     * @api
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param string $probeKeyPrefix
     * @param string $searchString
     * @param array<string, string> $fieldToSearchAnalyzer
     */
    public function consumeProbes(
        MsearchProbeBatcherInterface $batcher,
        string $probeKeyPrefix,
        string $searchString,
        array $fieldToSearchAnalyzer,
    ): QueryTermFrequencyResult;

    /**
     * Specification:
     * - Builds a fresh {@see MsearchProbeBatcherInterface} bound to the SAME Elastica client this fetcher
     *   itself uses — the seam that lets a collaborator with no `Elastica\Client` of its own (e.g.
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator}, standing alone
     *   outside the plugin's shared-batch orchestration) still get one to drive its own register/execute/
     *   consume cycle through.
     *
     * @api
     */
    public function createBatcher(): MsearchProbeBatcherInterface;
}
