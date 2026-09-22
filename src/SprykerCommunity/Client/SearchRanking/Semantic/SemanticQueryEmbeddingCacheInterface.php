<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Semantic;

interface SemanticQueryEmbeddingCacheInterface
{
    /**
     * Specification:
     * - Reads a previously cached query embedding for the given search string and embedding model id.
     * - Returns `null` on a cache miss, or when the cached value is not a valid JSON-encoded float array
     *   — never throws, so a corrupted/foreign cache entry degrades to "not cached" rather than an error.
     *
     * @api
     *
     * @param string $queryString
     * @param string $modelId
     *
     * @return array<int, float>|null
     */
    public function get(string $queryString, string $modelId): ?array;

    /**
     * Specification:
     * - Caches a query embedding for the given search string and embedding model id, JSON-encoded.
     *
     * @api
     *
     * @param string $queryString
     * @param string $modelId
     * @param array<int, float> $vector
     */
    public function set(string $queryString, string $modelId, array $vector): void;
}
