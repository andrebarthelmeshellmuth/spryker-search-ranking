<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Elastica\Client;
use Elastica\Request;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use Throwable;

/**
 * Resolves the index name via the injected {@see \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface}
 * rather than {@see \Spryker\Shared\Kernel\Store::getInstance()}, which throws on dynamic store mode.
 */
class QueryTermFrequencyFetcher implements QueryTermFrequencyFetcherInterface
{
    /**
     * @var string
     */
    protected const ENDPOINT_TERMVECTORS = '_termvectors';

    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     * @param \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface $storeClient
     */
    public function __construct(
        protected Client $elasticaClient,
        protected SearchElasticsearchConfig $searchElasticsearchConfig,
        protected SearchRankingToStoreClientInterface $storeClient,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $searchString
     * @param array<string, string> $fieldToSearchAnalyzer
     */
    public function fetch(string $searchString, array $fieldToSearchAnalyzer): QueryTermFrequencyResult
    {
        if ($searchString === '' || $fieldToSearchAnalyzer === []) {
            return new QueryTermFrequencyResult(0, []);
        }

        try {
            $responseData = $this->elasticaClient->request(
                sprintf('%s/%s', $this->resolveIndexName(), static::ENDPOINT_TERMVECTORS),
                Request::POST,
                [
                    'doc' => array_fill_keys(array_keys($fieldToSearchAnalyzer), $searchString),
                    'fields' => array_keys($fieldToSearchAnalyzer),
                    'per_field_analyzer' => $fieldToSearchAnalyzer,
                    'term_statistics' => true,
                    'field_statistics' => true,
                ],
            )->getData();
        } catch (Throwable) {
            return new QueryTermFrequencyResult(0, []);
        }

        return $this->extractResult($responseData);
    }

    /**
     * Combines per-field term vectors into one result: `doc_freq` via `max()` across every field the
     * term appears in at all (a term entirely absent from a field's own `terms` map contributes nothing
     * for that field, rather than a `0` that would drag an otherwise-positive max down), `docCount` via
     * `max()` of each field's own `field_statistics.doc_count` — an approximation when fields differ
     * slightly in how many documents populate them at all, see this package's README.
     *
     * @param array<string, mixed> $responseData
     */
    protected function extractResult(array $responseData): QueryTermFrequencyResult
    {
        $termVectorsByField = is_array($responseData['term_vectors'] ?? null) ? $responseData['term_vectors'] : [];

        $docCount = 0;
        $termDocumentFrequencies = [];

        foreach ($termVectorsByField as $fieldTermVector) {
            if (!is_array($fieldTermVector)) {
                continue;
            }

            $fieldStatistics = is_array($fieldTermVector['field_statistics'] ?? null) ? $fieldTermVector['field_statistics'] : [];
            $docCount = max($docCount, (int)($fieldStatistics['doc_count'] ?? 0));

            $terms = is_array($fieldTermVector['terms'] ?? null) ? $fieldTermVector['terms'] : [];

            foreach ($terms as $term => $termStatistics) {
                $documentFrequency = (int)($termStatistics['doc_freq'] ?? 0);
                $termDocumentFrequencies[$term] = max($termDocumentFrequencies[$term] ?? 0, $documentFrequency);
            }
        }

        return new QueryTermFrequencyResult($docCount, $termDocumentFrequencies);
    }

    protected function resolveIndexName(): string
    {
        $indexParameters = [
            $this->searchElasticsearchConfig->getIndexPrefix(),
            $this->storeClient->getCurrentStore()->getNameOrFail(),
            SharedSearchRankingConfig::PAGE_SOURCE_IDENTIFIER,
        ];

        return mb_strtolower(implode('_', array_filter($indexParameters)));
    }
}
