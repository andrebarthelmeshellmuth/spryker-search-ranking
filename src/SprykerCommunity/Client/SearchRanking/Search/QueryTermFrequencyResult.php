<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

/**
 * Immutable result of one {@see \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface}
 * probe: the corpus-wide document count (`N`, the index-wide `field_statistics.doc_count`, see this
 * package's README for the locale-scoping approximation this implies) and, per query term, how many
 * documents in the corpus contain it (`doc_freq`, `max()`-combined across every probed field). A term
 * absent from a field's term vector entirely contributes nothing for that field, not a `0` that would
 * lower an otherwise-positive max from another field.
 */
class QueryTermFrequencyResult
{
    /**
     * @param int $docCount
     * @param array<string, int> $termDocumentFrequencies
     */
    public function __construct(
        protected int $docCount,
        protected array $termDocumentFrequencies,
    ) {
    }

    /**
     * @return int
     */
    public function getDocCount(): int
    {
        return $this->docCount;
    }

    /**
     * Keyed by the exact token the search-time analyzer produced. A token entirely missing from the
     * corpus (never indexed anywhere) is still present here, with a value of `0` — not omitted — so a
     * consumer can tell "searched for, found nowhere" apart from "never part of the query at all".
     *
     * @return array<string, int>
     */
    public function getTermDocumentFrequencies(): array
    {
        return $this->termDocumentFrequencies;
    }
}
