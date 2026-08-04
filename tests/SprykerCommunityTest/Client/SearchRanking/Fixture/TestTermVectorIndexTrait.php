<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Fixture;

use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;

/**
 * A test-OWNED Elasticsearch index with two analyzed text fields, one of them ("full-text-ngram") using a
 * DIFFERENT index-time analyzer than its search-time one — deliberately separate from
 * `TestScoresIndexTrait` (which only carries the `scores` field this package's own mapping fragment
 * adds): {@see \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcher} tests need a real
 * engine to prove `per_field_analyzer` actually forces the SEARCH-time analyzer for `_termvectors`,
 * overriding what would otherwise default to the mismatched INDEX-time one. NEVER named after the real
 * resolved page index (`<prefix>_<store>_page`) — this index is only ever addressed by tests that inject
 * a `QueryTermFrequencyFetcher` pointed at it directly, so it can never collide with (and accidentally
 * wipe) a shop's real live catalog index.
 */
trait TestTermVectorIndexTrait
{
    /**
     * @var string
     */
    protected const TEST_TERM_VECTOR_INDEX_NAME = 'search_ranking_test_term_vector';

    /**
     * @var string
     */
    protected const TEST_TERM_VECTOR_FIELD_PLAIN = 'full-text';

    /**
     * Index-time analyzer is `edge_ngram_analyzer` (splits "chair" into "c", "ch", "cha", ...); search-time
     * is plain `standard` — the same shape this shop's own real `page.json` schema uses (edge-ngram
     * matching for partial/prefix search at index time, whole-token matching at search time).
     *
     * @var string
     */
    protected const TEST_TERM_VECTOR_FIELD_NGRAM = 'full-text-ngram';

    /**
     * @return void
     */
    protected function createTestTermVectorIndex(): void
    {
        $this->getTestTermVectorIndex()->create(
            [
                'settings' => [
                    'analysis' => [
                        'analyzer' => [
                            'edge_ngram_analyzer' => [
                                'tokenizer' => 'edge_ngram_tokenizer',
                                'filter' => ['lowercase'],
                            ],
                        ],
                        'tokenizer' => [
                            'edge_ngram_tokenizer' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 1,
                                'max_gram' => 10,
                                'token_chars' => ['letter', 'digit'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        static::TEST_TERM_VECTOR_FIELD_PLAIN => [
                            'type' => 'text',
                            'term_vector' => 'yes',
                        ],
                        static::TEST_TERM_VECTOR_FIELD_NGRAM => [
                            'type' => 'text',
                            'term_vector' => 'yes',
                            'analyzer' => 'edge_ngram_analyzer',
                            'search_analyzer' => 'standard',
                        ],
                    ],
                ],
            ],
            ['recreate' => true],
        );
    }

    /**
     * @return void
     */
    protected function deleteTestTermVectorIndex(): void
    {
        $index = $this->getTestTermVectorIndex();

        if (!$index->exists()) {
            return;
        }

        $index->delete();
    }

    /**
     * @param array<\Elastica\Document> $documents
     *
     * @return void
     */
    protected function indexTestTermVectorDocuments(array $documents): void
    {
        $index = $this->getTestTermVectorIndex();
        $index->addDocuments($documents);
        $index->refresh();
    }

    /**
     * @param string $id
     * @param string $fullText
     *
     * @return \Elastica\Document
     */
    protected function createTestTermVectorDocument(string $id, string $fullText): Document
    {
        return new Document($id, [
            static::TEST_TERM_VECTOR_FIELD_PLAIN => $fullText,
            static::TEST_TERM_VECTOR_FIELD_NGRAM => $fullText,
        ]);
    }

    /**
     * @return \Elastica\Index
     */
    protected function getTestTermVectorIndex(): Index
    {
        return $this->getTestElasticaClient()->getIndex(static::TEST_TERM_VECTOR_INDEX_NAME);
    }

    /**
     * @return \Elastica\Client
     */
    protected function getTestElasticaClient(): Client
    {
        $searchElasticsearchConfig = new SearchElasticsearchConfig();

        return (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());
    }
}
