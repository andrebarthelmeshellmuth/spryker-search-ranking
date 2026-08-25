<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Semantic;

use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStorageClientInterface;

/**
 * Caches query-time embeddings in key-value storage (Valkey/Redis via
 * {@see \Spryker\Client\Storage\StorageClientInterface}), so repeated identical searches don't re-hit the
 * embedding service. Deterministic key per (modelId, queryString) — cache semantics, not queue semantics
 * (unlike search-signals' `uniqid()`-keyed writes, which are one-shot event publishing, not something
 * meant to be read back by key).
 */
class SemanticQueryEmbeddingCache implements SemanticQueryEmbeddingCacheInterface
{
    /**
     * @var string
     */
    protected const KEY_PREFIX = 'search-ranking:query-embedding:';

    /**
     * @param \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStorageClientInterface $storageClient
     */
    public function __construct(protected SearchRankingToStorageClientInterface $storageClient)
    {
    }

    /**
     * @param string $queryString
     * @param string $modelId
     *
     * @return array<int, float>|null
     */
    public function get(string $queryString, string $modelId): ?array
    {
        $cachedValue = $this->storageClient->get($this->buildKey($queryString, $modelId));

        if (!is_string($cachedValue) || $cachedValue === '') {
            return null;
        }

        $decoded = json_decode($cachedValue, true);

        if (!is_array($decoded) || $decoded === []) {
            return null;
        }

        foreach ($decoded as $component) {
            if (!is_int($component) && !is_float($component)) {
                return null;
            }
        }

        return array_map('floatval', array_values($decoded));
    }

    /**
     * @param string $queryString
     * @param string $modelId
     * @param array<int, float> $vector
     */
    public function set(string $queryString, string $modelId, array $vector): void
    {
        $this->storageClient->set(
            $this->buildKey($queryString, $modelId),
            (string)json_encode(array_values($vector)),
        );
    }

    /**
     * @param string $queryString
     * @param string $modelId
     */
    protected function buildKey(string $queryString, string $modelId): string
    {
        return static::KEY_PREFIX . $modelId . ':' . sha1($queryString);
    }
}
