<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use Elastica\Client;
use Elastica\Exception\ConnectionException;
use Elastica\Response;
use SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcher;

/**
 * Query-shape unit test — a fake `Elastica\Client` records every request and returns a canned response,
 * the same convention {@see \SprykerCommunityTest\Client\SearchRanking\Intent\SuggestIndexEntityLookupTest}
 * already uses.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group MsearchProbeBatcherTest
 * @group Portable
 */
class MsearchProbeBatcherTest extends Unit
{
    public function testNeverFiresARequestWhenNothingWasRegistered(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient(['responses' => []]);
        $batcher = new MsearchProbeBatcher($fakeClient);

        // Act
        $batcher->execute();

        // Assert
        $this->assertCount(0, $fakeClient->requests);
        $this->assertNull($batcher->getResponseFor('anything'));
    }

    public function testFiresExactlyOneMsearchRequestBundlingEveryRegisteredProbe(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient([
            'responses' => [
                ['hits' => ['total' => ['value' => 3]]],
                ['hits' => ['total' => ['value' => 7]]],
            ],
        ]);
        $batcher = new MsearchProbeBatcher($fakeClient);

        $batcher->registerProbe('probe-a', 'index_a', ['size' => 0, 'query' => ['match_all' => (object)[]]]);
        $batcher->registerProbe('probe-b', 'index_b', ['size' => 0, 'query' => ['term' => ['field' => 'value']]]);

        // Act
        $batcher->execute();

        // Assert — exactly one HTTP round trip for both probes.
        $this->assertCount(1, $fakeClient->requests);
        $this->assertSame('_msearch', $fakeClient->requests[0]['path']);
        $this->assertSame('application/x-ndjson', $fakeClient->requests[0]['contentType']);

        $lines = explode("\n", rtrim($fakeClient->requests[0]['data']));
        $this->assertCount(4, $lines, 'NDJSON body must carry one header + one body line per registered probe.');
        $this->assertSame(['index' => 'index_a'], json_decode($lines[0], true));
        $this->assertSame(['index' => 'index_b'], json_decode($lines[2], true));
    }

    public function testRoutesEachResponseSliceBackToItsOwnRegistrationKey(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient([
            'responses' => [
                ['hits' => ['total' => ['value' => 3]]],
                ['hits' => ['total' => ['value' => 7]]],
            ],
        ]);
        $batcher = new MsearchProbeBatcher($fakeClient);

        $batcher->registerProbe('probe-a', 'index_a', ['size' => 0]);
        $batcher->registerProbe('probe-b', 'index_b', ['size' => 0]);

        // Act
        $batcher->execute();

        // Assert
        $responseA = $batcher->getResponseFor('probe-a');
        $responseB = $batcher->getResponseFor('probe-b');
        $this->assertNotNull($responseA);
        $this->assertNotNull($responseB);
        $this->assertSame(3, $responseA['hits']['total']['value']);
        $this->assertSame(7, $responseB['hits']['total']['value']);
    }

    public function testGetResponseForAnUnregisteredKeyReturnsNull(): void
    {
        // Arrange
        $fakeClient = $this->createFakeClient(['responses' => [['hits' => ['total' => ['value' => 1]]]]]);
        $batcher = new MsearchProbeBatcher($fakeClient);
        $batcher->registerProbe('probe-a', 'index_a', ['size' => 0]);

        // Act
        $batcher->execute();

        // Assert
        $this->assertNull($batcher->getResponseFor('never-registered'));
    }

    public function testATransportFailureDegradesEveryRegisteredProbeToNullRatherThanThrowing(): void
    {
        // Arrange
        $fakeClient = $this->createThrowingFakeClient();
        $batcher = new MsearchProbeBatcher($fakeClient);
        $batcher->registerProbe('probe-a', 'index_a', ['size' => 0]);

        // Act & Assert — never throws, mirrors the graceful-degradation discipline every other best-effort
        // probe in this package already follows.
        $batcher->execute();
        $this->assertNull($batcher->getResponseFor('probe-a'));
    }

    /**
     * @param array<string, mixed> $responseData
     */
    protected function createFakeClient(array $responseData): Client
    {
        return new class ($responseData) extends Client {
            /**
             * @var array<int, array{path: string, method: string, data: mixed, contentType: string}>
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
                unset($query);

                $this->requests[] = ['path' => $path, 'method' => $method, 'data' => $data, 'contentType' => $contentType];

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
             * @throws \Elastica\Exception\ConnectionException
             */
            public function request(string $path, string $method = 'GET', $data = [], array $query = [], string $contentType = 'application/json'): Response
            {
                unset($path, $method, $data, $query, $contentType);

                throw new ConnectionException('connection refused');
            }
        };
    }
}
