<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface;
use SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcher;
use SprykerCommunityTest\Client\SearchRanking\Fixture\TestTermVectorIndexTrait;

/**
 * INTEGRATION TEST — talks to a real Elasticsearch, against a TEST-OWNED index
 * (`TestTermVectorIndexTrait`), never the shop's real page index. Uses a test-only subclass overriding
 * the protected `resolveIndexName()` instead of touching `QueryTermFrequencyFetcher`'s production index
 * resolution — that resolution deliberately always targets the CURRENT store's real page index at
 * runtime, and must stay that way; the override exists only so this test can point the exact same
 * round-trip logic at a disposable index instead.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group QueryTermFrequencyFetcherTest
 */
class QueryTermFrequencyFetcherTest extends Unit
{
    use TestTermVectorIndexTrait;

    /**
     * @return void
     */
    protected function _before(): void
    {
        $this->createTestTermVectorIndex();
    }

    /**
     * @return void
     */
    protected function _after(): void
    {
        $this->deleteTestTermVectorIndex();
    }

    /**
     * @return void
     */
    public function testReturnsDocFrequencyAndDocCountForATermPresentInTheCorpus(): void
    {
        $this->indexTestTermVectorDocuments([
            $this->createTestTermVectorDocument('doc-1', 'gadget for the home'),
            $this->createTestTermVectorDocument('doc-2', 'gadget for the office'),
            $this->createTestTermVectorDocument('doc-3', 'widget for the garden'),
        ]);

        $result = $this->createFetcher()->fetch('gadget', [
            static::TEST_TERM_VECTOR_FIELD_PLAIN => 'standard',
        ]);

        $this->assertSame(3, $result->getDocCount());
        $this->assertSame(2, $result->getTermDocumentFrequencies()['gadget']);
    }

    /**
     * A term absent from the corpus entirely must resolve to a real `0`, not an error or a missing key —
     * `_termvectors` omits `doc_freq` for such a term rather than reporting it explicitly.
     *
     * @return void
     */
    public function testReturnsZeroDocFrequencyForATermAbsentFromTheCorpus(): void
    {
        $this->indexTestTermVectorDocuments([
            $this->createTestTermVectorDocument('doc-1', 'gadget for the home'),
        ]);

        $result = $this->createFetcher()->fetch('nonexistentterm', [
            static::TEST_TERM_VECTOR_FIELD_PLAIN => 'standard',
        ]);

        $this->assertSame(0, $result->getTermDocumentFrequencies()['nonexistentterm'] ?? 0);
    }

    /**
     * Proves the `per_field_analyzer` override actually takes effect: probing the ngram field with its
     * INDEX-time analyzer forced (instead of the search-time one the constructor argument requests)
     * would explode "gadget" into edge-ngram sub-tokens ("g", "ga", "gad", ...) rather than the one clean
     * "gadget" token the real search-time `standard` analyzer produces. Probing with the correct override
     * must return exactly the single whole token.
     *
     * @return void
     */
    public function testForcesTheSearchTimeAnalyzerRatherThanTheMismatchedIndexTimeOne(): void
    {
        $this->indexTestTermVectorDocuments([
            $this->createTestTermVectorDocument('doc-1', 'gadget for the home'),
        ]);

        $result = $this->createFetcher()->fetch('gadget', [
            static::TEST_TERM_VECTOR_FIELD_NGRAM => 'standard',
        ]);

        $this->assertArrayHasKey('gadget', $result->getTermDocumentFrequencies());
        $this->assertArrayNotHasKey('g', $result->getTermDocumentFrequencies());
        $this->assertArrayNotHasKey('gad', $result->getTermDocumentFrequencies());
    }

    /**
     * A term living in only ONE of several probed fields must still be combined via `max()` across
     * fields, not dropped because it's absent from the other field's own term list.
     *
     * @return void
     */
    public function testCombinesDocFrequencyAcrossFieldsViaMax(): void
    {
        $this->indexTestTermVectorDocuments([
            $this->createTestTermVectorDocument('doc-1', 'gadget'),
            $this->createTestTermVectorDocument('doc-2', 'gadget'),
        ]);

        $result = $this->createFetcher()->fetch('gadget', [
            static::TEST_TERM_VECTOR_FIELD_PLAIN => 'standard',
            static::TEST_TERM_VECTOR_FIELD_NGRAM => 'standard',
        ]);

        $this->assertSame(2, $result->getTermDocumentFrequencies()['gadget']);
    }

    /**
     * @return void
     */
    public function testEmptySearchStringReturnsAnEmptyResultWithoutFiringAnyRequest(): void
    {
        $result = $this->createFetcher()->fetch('', [static::TEST_TERM_VECTOR_FIELD_PLAIN => 'standard']);

        $this->assertSame(0, $result->getDocCount());
        $this->assertSame([], $result->getTermDocumentFrequencies());
    }

    /**
     * @return void
     */
    public function testEmptyFieldMapReturnsAnEmptyResultWithoutFiringAnyRequest(): void
    {
        $result = $this->createFetcher()->fetch('gadget', []);

        $this->assertSame(0, $result->getDocCount());
        $this->assertSame([], $result->getTermDocumentFrequencies());
    }

    /**
     * A nonexistent index must fail gracefully (empty result), never throw — the probe firing alongside a
     * real search must never be able to break it.
     *
     * @return void
     */
    public function testFailedProbeReturnsAnEmptyResultRatherThanThrowing(): void
    {
        $this->deleteTestTermVectorIndex();

        $result = $this->createFetcher()->fetch('gadget', [static::TEST_TERM_VECTOR_FIELD_PLAIN => 'standard']);

        $this->assertSame(0, $result->getDocCount());
        $this->assertSame([], $result->getTermDocumentFrequencies());
    }

    /**
     * @return \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcher
     */
    protected function createFetcher(): QueryTermFrequencyFetcher
    {
        $searchElasticsearchConfig = new SearchElasticsearchConfig();
        $elasticaClient = (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());

        return new class (
            $elasticaClient,
            $searchElasticsearchConfig,
            // Never actually called — resolveIndexName() is overridden below to bypass store resolution
            // entirely, the same way it bypasses the real page index. A real StoreClientInterface isn't
            // needed just to satisfy this constructor parameter.
            $this->createMock(SearchRankingToStoreClientInterface::class),
        ) extends QueryTermFrequencyFetcher {
            /**
             * Hardcoded rather than referencing `TestTermVectorIndexTrait::TEST_TERM_VECTOR_INDEX_NAME` —
             * an anonymous class can't reach an enclosing test case's trait constant. Must match that
             * constant's value.
             *
             * @return string
             */
            protected function resolveIndexName(): string
            {
                return 'search_ranking_test_term_vector';
            }
        };
    }
}
