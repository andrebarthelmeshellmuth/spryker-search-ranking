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
 * Pass 2 of "Intent-Aware Alpha": detects whether a REAL brand name (see
 * `data/import/common/common/product_attribute_key.csv` — `brand` is a plain product attribute) appears
 * as a word-boundary window WITHIN `searchString` — "Topstar swivel chair" detects "Topstar" without the
 * whole query equalling it, unlike {@see SkuIdentifierAnalyzer}'s whole-string match.
 *
 * Sets `detectedBrand` only — deliberately does NOT change any scoring/alpha behavior; wiring this signal
 * into actual formula behavior (e.g. shifting `relevanceWeight` for a navigational brand query) is
 * explicitly out of scope for this pass and left for a future one. Never throws — a lookup failure
 * degrades to "no match", the same discipline {@see QueryAnalyzerInterface::analyze()} documents.
 *
 * Brand/category disambiguation: this demoshop's own seed data indexes at least one placeholder-quality
 * value ("office", see `data/import/common/common/product_abstract.csv`) as a `brand` attribute even
 * though "office" is ALSO a genuine catalog category — a live instance of the classic "apple: brand or
 * fruit?" collision. When a candidate window matches the brand lookup AND the category lookup, this is
 * NOT treated as confident brand evidence: a generic word being "a brand too" is far more likely to be
 * low-quality/placeholder demo data than a genuine well-known brand name colliding with a category word,
 * so on ambiguity category wins and brand defers (does not set `detectedBrand` for that window, but keeps
 * scanning shorter/other windows for an unambiguous match). This does NOT read {@see CategoryAnalyzer}'s
 * output signal (that would violate analyzer independence) — it consults the SAME underlying
 * `EntityLookupInterface` category-scoped data source directly, the same way {@see CategoryAnalyzer} does.
 */
class BrandAnalyzer implements QueryAnalyzerInterface
{
    /**
     * @param \SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface $brandEntityLookup
     * @param \SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface $categoryEntityLookup
     */
    public function __construct(
        protected EntityLookupInterface $brandEntityLookup,
        protected EntityLookupInterface $categoryEntityLookup,
    ) {
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
            if (!$this->brandEntityLookup->exists($window)) {
                continue;
            }

            if ($this->categoryEntityLookup->exists($window)) {
                // Ambiguous: the identical window ALSO identifies a real category. Category wins by
                // default (see class docblock) — skip this window as brand evidence and keep scanning.
                continue;
            }

            return $queryContextTransfer->setDetectedBrand($window);
        }

        return $queryContextTransfer;
    }
}
