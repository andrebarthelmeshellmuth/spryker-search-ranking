<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Intent;

use Codeception\Test\Unit;
use Elastica\Client;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Elastica\Response;
use SprykerCommunity\Client\SearchRanking\Intent\SuggestIndexEntityLookup;

/**
 * Query-shape unit test — a fake `Elastica\Client` records every request and returns a canned response,
 * so this asserts the PHP-side request SHAPE (path, query DSL) without touching a real cluster. See
 * `Search/EngineCompatibilityCheckerTest`/`Search/QueryTermFrequencyFetcherTest` for this package's own
 * convention of a REAL integration test for Elastica-touching code elsewhere — deliberately not followed
 * here because the caller (Pass 2's own `search-ranking-optimizer` build task) asked for a mocked-client
 * query-shape test specifically; real cluster behavior is covered by this package's own live verification
 * instead (see README/CHANGELOG for the exact curl + console session).
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Intent
 * @group SuggestIndexEntityLookupTest
 * @group Portable
 */
class SuggestIndexEntityLookupTest extends Unit
{
    public function testExistsQueriesTermNormalizedAndTypeAsAFilterAgainstCount(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient(['count' => 1]);
        $entityLookup = new SuggestIndexEntityLookup($fakeClient, 'test_entity_lookup', 'sku');

        // Act
        $result = $entityLookup->exists('M23484');

        // Assert
        $this->assertTrue($result);
        $this->assertCount(1, $fakeClient->requests);
        $this->assertSame('test_entity_lookup/_count', $fakeClient->requests[0]['path']);
        $this->assertSame('m23484', $fakeClient->requests[0]['data']['query']['bool']['filter'][0]['term']['termNormalized']);
        $this->assertSame('sku', $fakeClient->requests[0]['data']['query']['bool']['filter'][1]['term']['type']);
    }

    public function testExistsReturnsFalseWhenCountIsZero(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient(['count' => 0]);
        $entityLookup = new SuggestIndexEntityLookup($fakeClient, 'test_entity_lookup', 'sku');

        // Act & Assert
        $this->assertFalse($entityLookup->exists('nonexistent'));
    }

    public function testExistsDegradesToFalseOnAnEngineFailure(): void
    {
        // Arrange
        $fakeClient = $this->createThrowingFakeClient();
        $entityLookup = new SuggestIndexEntityLookup($fakeClient, 'test_entity_lookup', 'sku');

        // Act & Assert — never throws, see EntityLookupInterface::exists()'s own contract.
        $this->assertFalse($entityLookup->exists('M23484'));
    }

    public function testExistsOnBlankTermReturnsFalseWithoutFiringARequest(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient(['count' => 1]);
        $entityLookup = new SuggestIndexEntityLookup($fakeClient, 'test_entity_lookup', 'sku');

        // Act & Assert
        $this->assertFalse($entityLookup->exists('   '));
        $this->assertCount(0, $fakeClient->requests);
    }

    public function testSuggestFiresACompletionSuggesterRequestAgainstTheTermField(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient([
            'suggest' => [
                'entityLookupSuggest' => [
                    [
                        'options' => [
                            ['_source' => ['term' => 'Topstar', 'type' => 'brand']],
                        ],
                    ],
                ],
            ],
        ]);
        $entityLookup = new SuggestIndexEntityLookup($fakeClient, 'test_entity_lookup', 'brand');

        // Act
        $result = $entityLookup->suggest('Top', 10);

        // Assert
        $this->assertSame(['Topstar'], $result);
        $this->assertSame('test_entity_lookup/_search', $fakeClient->requests[0]['path']);
        $this->assertSame('Top', $fakeClient->requests[0]['data']['suggest']['entityLookupSuggest']['prefix']);
        $this->assertSame('term', $fakeClient->requests[0]['data']['suggest']['entityLookupSuggest']['completion']['field']);
        $this->assertSame(50, $fakeClient->requests[0]['data']['suggest']['entityLookupSuggest']['completion']['size']);
    }

    public function testSuggestFiltersOutOptionsOfAnotherType(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient([
            'suggest' => [
                'entityLookupSuggest' => [
                    [
                        'options' => [
                            ['_source' => ['term' => 'M23484', 'type' => 'sku']],
                            ['_source' => ['term' => 'Topstar', 'type' => 'brand']],
                        ],
                    ],
                ],
            ],
        ]);
        $entityLookup = new SuggestIndexEntityLookup($fakeClient, 'test_entity_lookup', 'brand');

        // Act
        $result = $entityLookup->suggest('T', 10);

        // Assert
        $this->assertSame(['Topstar'], $result);
    }

    public function testSuggestDegradesToEmptyArrayOnAnEngineFailure(): void
    {
        // Arrange
        $fakeClient = $this->createThrowingFakeClient();
        $entityLookup = new SuggestIndexEntityLookup($fakeClient, 'test_entity_lookup', 'sku');

        // Act & Assert
        $this->assertSame([], $entityLookup->suggest('M', 10));
    }

    /**
     * @param array<string, mixed> $responseData
     */
    protected function createFakeClient(array $responseData): Client
    {
        return new class ($responseData) extends Client {
            /**
             * @var array<int, array{path: string, method: string, data: array<string, mixed>}>
             */
            public array $requests = [];

            public function __construct(protected array $responseData)
            {
            }

            /**
             * @param mixed $data
             * @param array<string, mixed> $query
             */
            public function request(string $path, string $method = 'GET', $data = [], array $query = [], string $contentType = 'application/json'): Response
            {
                unset($query, $contentType);

                $this->requests[] = ['path' => $path, 'method' => $method, 'data' => $data];

                return new Response($this->responseData, 200);
            }
        };
    }

    protected function createThrowingFakeClient(): Client
    {
        return new class extends Client {
            public function __construct()
            {
            }

            /**
             * @param mixed $data
             * @param array<string, mixed> $query
             *
             * @throws \Elastica\Exception\ResponseException
             */
            public function request(string $path, string $method = 'GET', $data = [], array $query = [], string $contentType = 'application/json'): Response
            {
                unset($query, $contentType);

                throw new ResponseException(
                    new Request($path, $method, $data),
                    new Response(['error' => 'boom'], 500),
                );
            }
        };
    }
}
