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
 * A test-OWNED Elasticsearch index carrying only the one field this package's own mapping fragment adds
 * (`scores`, dynamic) — same reasoning as SearchDebug's own `TestPageIndexTrait`: independent of whatever
 * `page.json` the host shop actually ships, so this suite stays portable to any shop installing the
 * package, and doesn't churn when a shop's own search config changes. No analysis settings are needed
 * here (unlike SearchDebug's fixture) — the `function_score` script only ever reads `scores.*` doc
 * values, it never depends on how `full-text` gets analyzed.
 */
trait TestScoresIndexTrait
{
    /**
     * @var string
     */
    protected const TEST_INDEX_NAME = 'search_ranking_test_scores';

    protected function createTestScoresIndex(): void
    {
        $this->getTestScoresIndex()->create(
            [
                'mappings' => [
                    'properties' => [
                        'scores' => [
                            'type' => 'object',
                            'dynamic' => true,
                        ],
                    ],
                ],
            ],
            ['recreate' => true],
        );
    }

    protected function deleteTestScoresIndex(): void
    {
        $index = $this->getTestScoresIndex();

        if (!$index->exists()) {
            return;
        }

        $index->delete();
    }

    /**
     * @param array<\Elastica\Document> $documents
     */
    protected function indexTestDocuments(array $documents): void
    {
        $index = $this->getTestScoresIndex();
        $index->addDocuments($documents);
        $index->refresh();
    }

    /**
     * @param string $id
     * @param array<string, mixed> $data
     */
    protected function createTestDocument(string $id, array $data): Document
    {
        return new Document($id, $data);
    }

    protected function getTestScoresIndex(): Index
    {
        return $this->getTestElasticaClient()->getIndex(static::TEST_INDEX_NAME);
    }

    /**
     * Same composition `SprykerCommunity\Client\SearchRanking\SearchRankingFactory::getElasticaClient()`
     * uses — both directly-instantiable value objects, no Locator/container needed.
     */
    protected function getTestElasticaClient(): Client
    {
        $searchElasticsearchConfig = new SearchElasticsearchConfig();

        return (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());
    }
}
