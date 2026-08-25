<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Semantic;

use Codeception\Test\Unit;
use ReflectionMethod;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException;
use SprykerCommunity\Client\SearchRanking\Semantic\TextEmbeddingsInferenceClient;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Semantic
 * @group TextEmbeddingsInferenceClientTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRanking\SearchRankingClientTester $tester
 * @group Portable
 */
class TextEmbeddingsInferenceClientTest extends Unit
{
    /**
     * No embedding service is reachable at this address (nothing listens on this loopback port),
     * so this proves the client turns a real connection failure into
     * {@see EmbeddingUnavailableException} rather than a raw curl error, a false return, or a fatal.
     */
    public function testEmbedThrowsAnEmbeddingUnavailableExceptionWhenTheServiceIsUnreachable(): void
    {
        $client = new TextEmbeddingsInferenceClient('http://127.0.0.1:1', 200, 500);

        $this->expectException(EmbeddingUnavailableException::class);

        $client->embed('a product name and description');
    }

    public function testParseResponseBodyReturnsTheFirstVectorAsFloats(): void
    {
        $client = new TextEmbeddingsInferenceClient('http://127.0.0.1:1');
        $method = new ReflectionMethod($client, 'parseResponseBody');
        $method->setAccessible(true);

        $vector = $method->invoke($client, '[[0.1, -0.2, 3]]');

        $this->assertSame([0.1, -0.2, 3.0], $vector);
    }

    public function testParseResponseBodyThrowsAnEmbeddingUnavailableExceptionForInvalidJson(): void
    {
        $client = new TextEmbeddingsInferenceClient('http://127.0.0.1:1');
        $method = new ReflectionMethod($client, 'parseResponseBody');
        $method->setAccessible(true);

        $this->expectException(EmbeddingUnavailableException::class);

        $method->invoke($client, 'not json');
    }

    public function testParseResponseBodyThrowsAnEmbeddingUnavailableExceptionForTheWrongShape(): void
    {
        $client = new TextEmbeddingsInferenceClient('http://127.0.0.1:1');
        $method = new ReflectionMethod($client, 'parseResponseBody');
        $method->setAccessible(true);

        $this->expectException(EmbeddingUnavailableException::class);

        $method->invoke($client, '{"error": "model not loaded"}');
    }
}
