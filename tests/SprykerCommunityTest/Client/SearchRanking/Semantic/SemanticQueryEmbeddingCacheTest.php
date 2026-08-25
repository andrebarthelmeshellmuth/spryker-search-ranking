<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Semantic;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\SemanticQueryEmbeddingCache;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Semantic
 * @group SemanticQueryEmbeddingCacheTest
 * Add your own group annotations below this line
 *
 * @group Portable
 */
class SemanticQueryEmbeddingCacheTest extends Unit
{
    public function testReturnsNullOnCacheMiss(): void
    {
        // Arrange
        $storageClientMock = $this->createMock(SearchRankingToStorageClientInterface::class);
        $storageClientMock->method('get')->willReturn(null);

        $cache = new SemanticQueryEmbeddingCache($storageClientMock);

        // Act
        $result = $cache->get('gadget', 'sentence-transformers/all-MiniLM-L6-v2');

        // Assert
        $this->assertNull($result);
    }

    public function testReturnsNullOnMalformedCachedValue(): void
    {
        // Arrange
        $storageClientMock = $this->createMock(SearchRankingToStorageClientInterface::class);
        $storageClientMock->method('get')->willReturn('not valid json');

        $cache = new SemanticQueryEmbeddingCache($storageClientMock);

        // Act
        $result = $cache->get('gadget', 'sentence-transformers/all-MiniLM-L6-v2');

        // Assert
        $this->assertNull($result);
    }

    public function testReturnsNullWhenCachedValueIsNotAFlatNumericArray(): void
    {
        // Arrange
        $storageClientMock = $this->createMock(SearchRankingToStorageClientInterface::class);
        $storageClientMock->method('get')->willReturn(json_encode(['a' => 'b']));

        $cache = new SemanticQueryEmbeddingCache($storageClientMock);

        // Act
        $result = $cache->get('gadget', 'sentence-transformers/all-MiniLM-L6-v2');

        // Assert
        $this->assertNull($result);
    }

    public function testReturnsTheDecodedVectorOnCacheHit(): void
    {
        // Arrange
        $storageClientMock = $this->createMock(SearchRankingToStorageClientInterface::class);
        $storageClientMock->method('get')->willReturn(json_encode([0.1, -0.2, 0.3]));

        $cache = new SemanticQueryEmbeddingCache($storageClientMock);

        // Act
        $result = $cache->get('gadget', 'sentence-transformers/all-MiniLM-L6-v2');

        // Assert
        $this->assertSame([0.1, -0.2, 0.3], $result);
    }

    public function testGetUsesADeterministicKeyBasedOnModelIdAndQueryString(): void
    {
        // Arrange
        $expectedKey = 'search-ranking:query-embedding:sentence-transformers/all-MiniLM-L6-v2:' . sha1('gadget');

        $storageClientMock = $this->createMock(SearchRankingToStorageClientInterface::class);
        $storageClientMock->expects($this->once())
            ->method('get')
            ->with($expectedKey)
            ->willReturn(null);

        $cache = new SemanticQueryEmbeddingCache($storageClientMock);

        // Act
        $cache->get('gadget', 'sentence-transformers/all-MiniLM-L6-v2');
    }

    public function testSetWritesTheJsonEncodedVectorUnderTheDeterministicKey(): void
    {
        // Arrange
        $expectedKey = 'search-ranking:query-embedding:sentence-transformers/all-MiniLM-L6-v2:' . sha1('gadget');

        $storageClientMock = $this->createMock(SearchRankingToStorageClientInterface::class);
        $storageClientMock->expects($this->once())
            ->method('set')
            ->with($expectedKey, json_encode([0.1, 0.2]));

        $cache = new SemanticQueryEmbeddingCache($storageClientMock);

        // Act
        $cache->set('gadget', 'sentence-transformers/all-MiniLM-L6-v2', [0.1, 0.2]);
    }

    /**
     * A repeated identical search (same query string + model id) must round-trip through get()/set()
     * to the exact same vector, proving cache semantics (deterministic keys), not queue semantics.
     */
    public function testSetThenGetRoundTripsTheSameVector(): void
    {
        // Arrange
        $store = [];
        $storageClientMock = $this->createMock(SearchRankingToStorageClientInterface::class);
        $storageClientMock->method('set')->willReturnCallback(function (string $key, $value) use (&$store): void {
            $store[$key] = $value;
        });
        $storageClientMock->method('get')->willReturnCallback(function (string $key) use (&$store) {
            return $store[$key] ?? null;
        });

        $cache = new SemanticQueryEmbeddingCache($storageClientMock);

        // Act
        $cache->set('gadget', 'model-a', [0.5, -0.5, 1.0]);
        $result = $cache->get('gadget', 'model-a');

        // Assert
        $this->assertSame([0.5, -0.5, 1.0], $result);
    }
}
