<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchRanking\Intent;

/**
 * Bounded word n-gram windows over a free-text query string — shared by {@see \SprykerCommunity\Client\SearchRanking\Intent\BrandAnalyzer}
 * and {@see \SprykerCommunity\Client\SearchRanking\Intent\CategoryAnalyzer} to detect a KNOWN entity name
 * (a brand, a category) appearing as a SUBSTRING/word-boundary match within a longer query — "Topstar
 * swivel chair" needs to recognize "Topstar" as a brand without the whole query equalling it, unlike
 * {@see \SprykerCommunity\Client\SearchRanking\Intent\SkuIdentifierAnalyzer}'s whole-string match.
 *
 * Deliberately simple and bounded, not a general NLP tokenizer: real catalog queries in this project are
 * short (a handful of words), and both callers only need a handful of candidate windows per query, not
 * exhaustive n-gram coverage of long text.
 */
class QueryWindowExtractor
{
    /**
     * @var int
     */
    protected const MAX_WINDOW_WORDS = 3;

    /**
     * @var int
     */
    protected const MAX_TOKENS = 12;

    /**
     * Specification:
     * - Splits `$searchString` on whitespace into up to {@see MAX_TOKENS} tokens (extra tokens beyond that
     *   are ignored — a deliberate bound, not a truncation bug: a query this long is not the kind of short
     *   navigational/brand/category query this signal exists to detect), then returns every contiguous
     *   1-, 2-, and 3-word window over those tokens, longest windows first (a multi-word real entity name
     *   should win over a shorter false-positive substring of it).
     * - Never throws; an empty/whitespace-only `$searchString` returns an empty array.
     *
     * @param string $searchString
     *
     * @return array<int, string>
     */
    public static function extractWindows(string $searchString): array
    {
        $tokens = array_slice(
            array_values(array_filter(preg_split('/\s+/', trim($searchString)) ?: [], static fn (string $token): bool => $token !== '')),
            0,
            static::MAX_TOKENS,
        );

        $tokenCount = count($tokens);

        if ($tokenCount === 0) {
            return [];
        }

        $windows = [];

        for ($windowSize = min(static::MAX_WINDOW_WORDS, $tokenCount); $windowSize >= 1; $windowSize--) {
            for ($start = 0; $start <= $tokenCount - $windowSize; $start++) {
                $windows[] = implode(' ', array_slice($tokens, $start, $windowSize));
            }
        }

        return $windows;
    }
}
