<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

/**
 * @internal A clean seam over "does this term identify a real catalog entity" — deliberately holds NO
 * assumption about where the entity set lives. {@see SuggestIndexEntityLookup} (OpenSearch
 * `completion`-suggester-backed, always-on for `sku`/`brand`/`category`) is the sole real storage
 * implementation; {@see CachingEntityLookupDecorator} is the second implementer, wrapping it to read from
 * a pre-fetched shared `_msearch` batch instead of firing its own request per call.
 */
interface EntityLookupInterface
{
    /**
     * Specification:
     * - Whether `$term` (expected pre-normalized by the caller — case/whitespace handling is the caller's
     *   concern, not this interface's) identifies a real catalog entity in this lookup's own scope.
     * - Never throws: a lookup failure (engine unavailable, corrupted data) must degrade to `false`, not an
     *   error.
     *
     * @api
     *
     * @param string $term
     */
    public function exists(string $term): bool;

    /**
     * Specification:
     * - Up to `$limit` real entity values whose normalized form starts with or contains `$prefix`.
     * - Never throws: returns an empty array on any failure.
     *
     * @api
     *
     * @param string $prefix
     * @param int $limit
     *
     * @return array<int, string>
     */
    public function suggest(string $prefix, int $limit): array;
}
