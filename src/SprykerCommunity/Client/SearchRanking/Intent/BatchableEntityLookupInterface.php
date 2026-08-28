<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

/**
 * @internal Optional extension of {@see EntityLookupInterface}, implemented ONLY by a lookup backed by a
 * real Elasticsearch/OpenSearch probe of its own (today: {@see SuggestIndexEntityLookup}, the sole
 * real-storage implementation) — NOT by {@see CachingEntityLookupDecorator}, which wraps one instead of
 * owning a probe itself.
 *
 * Splits {@see EntityLookupInterface::exists()}'s single synchronous call into a build-request/parse-
 * response pair, WITHOUT changing `exists()` itself — see {@see SuggestIndexEntityLookup}'s own docblock
 * for why its standalone `exists()` still fires its own single `_count` request unchanged. These methods
 * exist purely so a caller (see {@see CachingEntityLookupDecorator}) can register the SAME lookup this way
 * against a SHARED batch instead.
 */
interface BatchableEntityLookupInterface extends EntityLookupInterface
{
    /**
     * The index this lookup's probes target — needed by a caller registering this lookup's probes onto a
     * shared {@see \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface}, whose
     * `_msearch` batch may span probes against several different indices at once.
     *
     * @api
     */
    public function getIndexName(): string;

    /**
     * Specification:
     * - Builds the `_msearch`-batchable, `size:0` count-query body for "does `$term` exist in this lookup's
     *   scope" — the batch-shaped counterpart of the single-probe `_count` query `exists()` fires on its
     *   own. Deliberately a SEPARATE query shape (`size:0` search vs. `_count`), not a refactor of the
     *   existing `exists()` internals, to keep `exists()`'s own request/response shape — and its existing
     *   test coverage — completely untouched.
     * - Never throws: building a query body is pure data construction, nothing that can fail.
     *
     * @api
     *
     * @param string $term
     *
     * @return array<string, mixed>
     */
    public function buildBatchExistsProbeRequest(string $term): array;

    /**
     * Parses the response slice a `_msearch` batch returned for one {@see buildBatchExistsProbeRequest()}
     * probe (a `hits.total.value`-shaped `_search` response) back into the same `bool` {@see EntityLookupInterface::exists()}
     * would have returned for that same term. `null` (probe never registered, or the batch failed at the
     * transport level) degrades to `false` — the same graceful-degradation discipline `exists()` itself
     * follows.
     *
     * @api
     *
     * @param array<string, mixed>|null $responseData
     */
    public function parseBatchExistsProbeResponse(?array $responseData): bool;
}
