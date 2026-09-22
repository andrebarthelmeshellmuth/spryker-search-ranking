<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

/**
 * Case-insensitive, whitespace-collapsed term normalization — the SAME normalization used on both the
 * write side (`search-ranking:entity-lookup:suggest-index:rebuild`, via
 * {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupIndexer}, which
 * populates `termNormalized`) and every read here ({@see SuggestIndexEntityLookup},
 * {@see CachingEntityLookupDecorator}), so a lookup never silently misses a real entity only because of
 * casing/spacing. Extracted as its own small utility (formerly a static method on the now-removed
 * KV-backed `InMemoryEntityLookup`) since it's a pure string-normalization concern with no relationship to
 * any one storage tier.
 */
class EntityTermNormalizer
{
    /**
     * @param string $term
     */
    public static function normalize(string $term): string
    {
        return mb_strtolower(trim((string)preg_replace('/\s+/', ' ', $term)));
    }
}
