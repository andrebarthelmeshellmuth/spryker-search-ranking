<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Shared\SearchRanking\Intent\QueryWindowExtractor;

/**
 * Pass 2 of "Intent-Aware Alpha": detects whether a REAL category name (`spy_category_attribute.name`)
 * appears as a word-boundary window WITHIN `searchString` — same phrase-within-query detection as
 * {@see BrandAnalyzer}, just against the category name set instead of brand names.
 *
 * Sets `detectedCategory` only — same "detection only, no scoring change" scope limit as
 * {@see BrandAnalyzer}. Never throws.
 *
 * Brand/category disambiguation: deliberately NOT mirrored here. {@see BrandAnalyzer} defers to category
 * on an ambiguous window (a term matching both lookups) because a generic word being "a brand too" is far
 * more likely to be low-quality/placeholder demo data than a genuine well-known brand colliding with a
 * category word. Since category already wins in that collision, this analyzer needs no extra check of its
 * own — it keeps detecting every window that matches the category lookup, ambiguous or not.
 */
class CategoryAnalyzer implements QueryAnalyzerInterface
{
    /**
     * @param \SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface $categoryEntityLookup
     */
    public function __construct(protected EntityLookupInterface $categoryEntityLookup)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    public function analyze(SearchRankingQueryContextTransfer $queryContextTransfer): SearchRankingQueryContextTransfer
    {
        $searchString = $queryContextTransfer->getSearchStringOrFail();

        if (trim($searchString) === '') {
            return $queryContextTransfer;
        }

        foreach (QueryWindowExtractor::extractWindows($searchString) as $window) {
            if ($this->categoryEntityLookup->exists($window)) {
                return $queryContextTransfer->setDetectedCategory($window);
            }
        }

        return $queryContextTransfer;
    }
}
