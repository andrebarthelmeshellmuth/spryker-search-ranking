<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Intent;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchRanking\Intent\BatchableEntityLookupInterface;
use SprykerCommunity\Client\SearchRanking\Intent\CachingEntityLookupDecorator;
use SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Intent
 * @group CachingEntityLookupDecoratorTest
 * @group Portable
 */
class CachingEntityLookupDecoratorTest extends Unit
{
    /**
     * A term whose probe was pre-registered AND whose response is already sitting in the batcher must be
     * answered purely from that cached response — the inner lookup's own standalone `exists()` must never
     * be called.
     */
    public function testExistsReadsFromTheBatchedResponseWhenTheProbeWasPreRegistered(): void
    {
        // Arrange
        $innerLookup = $this->createInnerLookup(standaloneExistsResult: false);
        $batcher = $this->createBatcher(['entity:sku:m23484' => true]);
        $decorator = new CachingEntityLookupDecorator($innerLookup, $batcher, 'entity:sku');

        // Act
        $decorator->registerProbe('M23484');
        $result = $decorator->exists('M23484');

        // Assert
        $this->assertTrue($result);
        $this->assertSame(0, $innerLookup->standaloneExistsCallCount, 'Cache-hit path must not fall back to the standalone exists() request.');
        $this->assertSame(['entity:sku:m23484'], $innerLookup->parsedProbeKeys);
    }

    /**
     * A term the batcher has no response for (never registered, or registered under a caller other than
     * the batched orchestration) must degrade to the inner lookup's own standalone, uncached `exists()`.
     */
    public function testExistsFallsBackToTheStandaloneLookupWhenTheTermWasNotPreRegistered(): void
    {
        // Arrange
        $innerLookup = $this->createInnerLookup(standaloneExistsResult: true);
        $batcher = $this->createBatcher([]);
        $decorator = new CachingEntityLookupDecorator($innerLookup, $batcher, 'entity:brand');

        // Act
        // Deliberately no registerProbe() call first.
        $result = $decorator->exists('topstar');

        // Assert
        $this->assertTrue($result);
        $this->assertSame(1, $innerLookup->standaloneExistsCallCount);
        $this->assertSame(['topstar'], $innerLookup->standaloneExistsTerms);
    }

    /**
     * A probe that WAS registered but whose batch response is missing (transport-level batch failure) must
     * also degrade to the standalone fallback, not a false-negative `exists() === false`.
     */
    public function testExistsFallsBackToTheStandaloneLookupWhenTheBatchNeverExecuted(): void
    {
        // Arrange
        $innerLookup = $this->createInnerLookup(standaloneExistsResult: true);
        $batcher = $this->createBatcher([]);
        $decorator = new CachingEntityLookupDecorator($innerLookup, $batcher, 'entity:category');

        // Act
        $decorator->registerProbe('Gadgets');
        $result = $decorator->exists('Gadgets');

        // Assert
        $this->assertTrue($result);
        $this->assertSame(1, $innerLookup->standaloneExistsCallCount);
    }

    /**
     * A blank/whitespace-only term normalizes to '' and short-circuits to `false` without ever touching the
     * batcher or the inner lookup.
     */
    public function testExistsReturnsFalseForABlankTermWithoutTouchingTheBatcherOrInnerLookup(): void
    {
        // Arrange
        $innerLookup = $this->createInnerLookup(standaloneExistsResult: true);
        $batcher = $this->createBatcher([]);
        $decorator = new CachingEntityLookupDecorator($innerLookup, $batcher, 'entity:sku');

        // Act
        $result = $decorator->exists('   ');

        // Assert
        $this->assertFalse($result);
        $this->assertSame(0, $innerLookup->standaloneExistsCallCount);
        $this->assertSame([], $batcher->getResponseForCalls);
    }

    /**
     * registerProbe() for a blank term must also be a no-op — never registered onto the batcher.
     */
    public function testRegisterProbeIgnoresABlankTerm(): void
    {
        // Arrange
        $innerLookup = $this->createInnerLookup(standaloneExistsResult: false);
        $batcher = $this->createBatcher([]);
        $decorator = new CachingEntityLookupDecorator($innerLookup, $batcher, 'entity:sku');

        // Act
        $decorator->registerProbe(' ');

        // Assert
        $this->assertSame([], $batcher->registeredProbes);
    }

    /**
     * The probe key registered onto the batcher must be built from the caller's prefix plus the
     * NORMALIZED term (case/whitespace-insensitive), matching the same key `exists()` later looks up under
     * — differently-cased/spaced calls for the "same" term must land on the same cache entry.
     */
    public function testRegisterProbeAndExistsAgreeOnTheSameNormalizedProbeKey(): void
    {
        // Arrange
        $innerLookup = $this->createInnerLookup(standaloneExistsResult: false);
        $batcher = $this->createBatcher(['entity:sku:m23484' => true]);
        $decorator = new CachingEntityLookupDecorator($innerLookup, $batcher, 'entity:sku');

        // Act
        $decorator->registerProbe('  M23484  ');
        $result = $decorator->exists('m23484');

        // Assert
        $this->assertSame(['entity:sku:m23484'], array_keys($batcher->registeredProbes));
        $this->assertTrue($result);
    }

    /**
     * suggest() is out of scope for batching and must always delegate straight through to the inner
     * lookup, unconditionally.
     */
    public function testSuggestAlwaysDelegatesToTheInnerLookup(): void
    {
        // Arrange
        $innerLookup = $this->createInnerLookup(standaloneExistsResult: false, suggestResult: ['topstar', 'toplux']);
        $batcher = $this->createBatcher([]);
        $decorator = new CachingEntityLookupDecorator($innerLookup, $batcher, 'entity:brand');

        // Act
        $result = $decorator->suggest('top', 5);

        // Assert
        $this->assertSame(['topstar', 'toplux'], $result);
        $this->assertSame([['top', 5]], $innerLookup->suggestCalls);
    }

    /**
     * A minimal in-memory {@see BatchableEntityLookupInterface} test double. Standalone `exists()` calls
     * and parsed-probe-response calls are recorded so tests can assert on them directly.
     *
     * @param array<int, string> $suggestResult
     */
    protected function createInnerLookup(bool $standaloneExistsResult, array $suggestResult = []): BatchableEntityLookupInterface
    {
        return new class ($standaloneExistsResult, $suggestResult) implements BatchableEntityLookupInterface {
            public int $standaloneExistsCallCount = 0;

            /**
             * @var array<int, string>
             */
            public array $standaloneExistsTerms = [];

            /**
             * @var array<int, string>
             */
            public array $parsedProbeKeys = [];

            /**
             * @var array<int, array{0: string, 1: int}>
             */
            public array $suggestCalls = [];

            /**
             * @param array<int, string> $suggestResult
             */
            public function __construct(
                protected bool $standaloneExistsResult,
                protected array $suggestResult,
            ) {
            }

            public function getIndexName(): string
            {
                return 'entity-lookup-suggest';
            }

            /**
             * @return array<string, mixed>
             */
            public function buildBatchExistsProbeRequest(string $term): array
            {
                return ['size' => 0, 'query' => ['term' => ['termNormalized' => $term]]];
            }

            /**
             * @param array<string, mixed>|null $responseData
             */
            public function parseBatchExistsProbeResponse(?array $responseData): bool
            {
                if ($responseData === null) {
                    return false;
                }

                $this->parsedProbeKeys[] = (string)($responseData['__key'] ?? '');

                return (bool)($responseData['__hit'] ?? false);
            }

            public function exists(string $term): bool
            {
                $this->standaloneExistsCallCount++;
                $this->standaloneExistsTerms[] = $term;

                return $this->standaloneExistsResult;
            }

            /**
             * @return array<int, string>
             */
            public function suggest(string $prefix, int $limit): array
            {
                $this->suggestCalls[] = [$prefix, $limit];

                return $this->suggestResult;
            }
        };
    }

    /**
     * A minimal in-memory {@see MsearchProbeBatcherInterface} test double. Responses are pre-seeded by key
     * (simulating an already-executed batch); `registerProbe()` just records what was registered so tests
     * can assert on it.
     *
     * @param array<string, bool> $hitByKey Which pre-seeded keys should resolve as a "hit" once wrapped by
     * the inner lookup's `parseBatchExistsProbeResponse()`.
     */
    protected function createBatcher(array $hitByKey): MsearchProbeBatcherInterface
    {
        return new class ($hitByKey) implements MsearchProbeBatcherInterface {
            /**
             * @var array<string, array{0: string, 1: array<string, mixed>}>
             */
            public array $registeredProbes = [];

            /**
             * @var array<int, string>
             */
            public array $getResponseForCalls = [];

            /**
             * @param array<string, bool> $hitByKey
             */
            public function __construct(protected array $hitByKey)
            {
            }

            /**
             * @param array<string, mixed> $queryBody
             */
            public function registerProbe(string $key, string $indexName, array $queryBody): void
            {
                $this->registeredProbes[$key] = [$indexName, $queryBody];
            }

            public function execute(): void
            {
                // No-op: this fake seeds its responses at construction time instead of firing a real batch.
            }

            /**
             * @return array<string, mixed>|null
             */
            public function getResponseFor(string $key): ?array
            {
                $this->getResponseForCalls[] = $key;

                if (!array_key_exists($key, $this->hitByKey)) {
                    return null;
                }

                return ['__key' => $key, '__hit' => $this->hitByKey[$key]];
            }
        };
    }
}
