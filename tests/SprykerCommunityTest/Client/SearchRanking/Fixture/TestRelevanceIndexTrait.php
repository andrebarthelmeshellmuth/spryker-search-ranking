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
 * A test-OWNED Elasticsearch index with a plain analyzed text field — deliberately separate from
 * `TestScoresIndexTrait` (which only carries the `scores` field this package's own mapping fragment
 * adds): entropy-probe tests need real, engine-computed BM25 `_score` values from an actual text match,
 * not the `scores.*` doc values `function_score` reads. NEVER named after the real resolved page index
 * (`<prefix>_<store>_page`) — this index is only ever addressed by tests that override
 * `EntropyWeightCalculator::resolveIndexName()` to point at it, so it can never collide with (and
 * accidentally wipe) a shop's real live catalog index.
 */
trait TestRelevanceIndexTrait
{
    /**
     * @var string
     */
    protected const TEST_RELEVANCE_INDEX_NAME = 'search_ranking_test_relevance';

    /**
     * @return void
     */
    protected function createTestRelevanceIndex(): void
    {
        $this->getTestRelevanceIndex()->create(
            [
                'mappings' => [
                    'properties' => [
                        'full-text' => [
                            'type' => 'text',
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
    protected function deleteTestRelevanceIndex(): void
    {
        $index = $this->getTestRelevanceIndex();

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
    protected function indexTestRelevanceDocuments(array $documents): void
    {
        $index = $this->getTestRelevanceIndex();
        $index->addDocuments($documents);
        $index->refresh();
    }

    /**
     * @param string $id
     * @param string $fullText
     *
     * @return \Elastica\Document
     */
    protected function createTestRelevanceDocument(string $id, string $fullText): Document
    {
        return new Document($id, ['full-text' => $fullText]);
    }

    /**
     * @return \Elastica\Index
     */
    protected function getTestRelevanceIndex(): Index
    {
        return $this->getTestElasticaClient()->getIndex(static::TEST_RELEVANCE_INDEX_NAME);
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
