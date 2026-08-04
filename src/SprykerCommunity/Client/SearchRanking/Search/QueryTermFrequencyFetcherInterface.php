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
     * - Fires ONE `_termvectors` request against an artificial document (`$searchString` placed verbatim
     *   into every field of `$fieldToSearchAnalyzer`) to learn, per query term, how many documents in the
     *   corpus actually contain it — without running the real search query at all.
     * - `$fieldToSearchAnalyzer` maps each field to probe (e.g. `full-text-boosted`) to the analyzer name
     *   to force via `per_field_analyzer` — required because `_termvectors` defaults to a field's
     *   INDEX-time analyzer, not its search-time one, which can tokenize very differently (e.g. an
     *   edge-ngram index analyzer exploding one search token into several index-time sub-tokens).
     * - Probes every given field and combines each term's `doc_freq` via `max()` across fields — some
     *   catalogs (e.g. SKU-derived content) only populate certain fields, so a single-field probe can
     *   silently under-report.
     * - A term missing its `doc_freq`/`ttf` key in the response is a genuine `0`, not an error — resolved
     *   to `0` in the returned result rather than surfaced as an exception.
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
}
