<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;

/**
 * Detects whether the current query's search string IS an exact product identifier (SKU/model number):
 * `$skuEntityLookup->exists()` against the full real SKU set this project's catalog holds (populated by
 * `search-ranking:entity-lookup:suggest-index:rebuild --type=sku`, see
 * {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupRebuilder}). Sets
 * `matchedIdentifierValue` to the real, verbatim search string on a hit.
 *
 * Formerly a two-tier detector (exact dictionary hit, then a shape-regex fallback for a SKU-shaped query
 * not yet in the dictionary) — the shape-regex tier was dropped once `sku` moved onto the mandatory
 * ES-backed entity-lookup index (always kept in sync via the suggest-index rebuild), removing the "might
 * not be in the dictionary yet" gap that tier existed to paper over.
 *
 * Never throws: a lookup failure degrades to "no match", the same graceful-degradation discipline
 * {@see QueryAnalyzerInterface::analyze()} documents.
 */
class SkuIdentifierAnalyzer implements QueryAnalyzerInterface
{
    /**
     * @param \SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface $skuEntityLookup
     */
    public function __construct(protected EntityLookupInterface $skuEntityLookup)
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

        if ($this->skuEntityLookup->exists($searchString)) {
            return $queryContextTransfer
                ->setIsIdentifierMatch(true)
                ->setMatchedIdentifierValue($searchString);
        }

        return $queryContextTransfer;
    }
}
