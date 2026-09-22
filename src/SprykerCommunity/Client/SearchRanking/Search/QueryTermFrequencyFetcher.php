<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Elastica\Client;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

/**
 * Resolves the index name via the injected {@see \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface}
 * rather than {@see \Spryker\Shared\Kernel\Store::getInstance()}, which throws on dynamic store mode.
 *
 * Rewritten from a single `_termvectors` probe onto N `size:0` `match` count-sub-queries fired through
 * {@see MsearchProbeBatcherInterface} — `_termvectors` cannot ride in an `_msearch` batch at all (a
 * fundamentally different endpoint), so this shape is what makes it possible to collapse specificity's own
 * probe together with other collaborators' probes (entity-lookup exists-checks) into ONE round trip. See
 * {@see QueryTermFrequencyFetcherInterface::fetch()} for the full rationale and the known
 * `_termvectors`-vs-`match`-query semantic differences this rewrite accepts.
 */
class QueryTermFrequencyFetcher implements QueryTermFrequencyFetcherInterface
{
    /**
     * @var string
     */
    protected const RESPONSE_KEY_DOC_COUNT_SUFFIX = '::docCount';

    /**
     * @var string
     */
    protected const RESPONSE_KEY_TERM_SEPARATOR = '::term::';

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
     * @api
     *
     * @param string $searchString
     * @param array<string, string> $fieldToSearchAnalyzer
     */
    public function fetch(string $searchString, array $fieldToSearchAnalyzer): QueryTermFrequencyResult
    {
        if ($searchString === '' || $fieldToSearchAnalyzer === []) {
            return new QueryTermFrequencyResult(0, []);
        }

        $batcher = $this->createBatcher();
        $this->registerProbes($batcher, 'qtf', $searchString, $fieldToSearchAnalyzer);
        $batcher->execute();

        return $this->consumeProbes($batcher, 'qtf', $searchString, $fieldToSearchAnalyzer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param string $probeKeyPrefix
     * @param string $searchString
     * @param array<string, string> $fieldToSearchAnalyzer
     */
    public function registerProbes(
        MsearchProbeBatcherInterface $batcher,
        string $probeKeyPrefix,
        string $searchString,
        array $fieldToSearchAnalyzer,
    ): void {
        if ($searchString === '' || $fieldToSearchAnalyzer === []) {
            return;
        }

        $indexName = $this->resolveIndexName();

        $batcher->registerProbe(
            $this->buildDocCountKey($probeKeyPrefix),
            $indexName,
            ['size' => 0, 'query' => ['match_all' => (object)[]]],
        );

        foreach ($fieldToSearchAnalyzer as $field => $analyzer) {
            foreach ($this->extractTerms($searchString) as $term) {
                $batcher->registerProbe(
                    $this->buildTermKey($probeKeyPrefix, $field, $term),
                    $indexName,
                    [
                        'size' => 0,
                        'query' => ['match' => [$field => ['query' => $term, 'analyzer' => $analyzer]]],
                    ],
                );
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param string $probeKeyPrefix
     * @param string $searchString
     * @param array<string, string> $fieldToSearchAnalyzer
     */
    public function consumeProbes(
        MsearchProbeBatcherInterface $batcher,
        string $probeKeyPrefix,
        string $searchString,
        array $fieldToSearchAnalyzer,
    ): QueryTermFrequencyResult {
        if ($searchString === '' || $fieldToSearchAnalyzer === []) {
            return new QueryTermFrequencyResult(0, []);
        }

        $docCount = $this->extractHitsTotal($batcher->getResponseFor($this->buildDocCountKey($probeKeyPrefix)));

        if ($docCount <= 0) {
            // Nothing meaningful can be measured about term rarity without a real corpus size to divide
            // by — same "no usable signal" outcome a total probe failure produced under the old
            // `_termvectors` shape (docCount 0, empty term map), not a map of misleading all-zero entries.
            return new QueryTermFrequencyResult(0, []);
        }

        $termDocumentFrequencies = [];

        foreach ($this->extractTerms($searchString) as $term) {
            $documentFrequency = 0;

            foreach (array_keys($fieldToSearchAnalyzer) as $field) {
                $responseData = $batcher->getResponseFor($this->buildTermKey($probeKeyPrefix, $field, $term));
                $documentFrequency = max($documentFrequency, $this->extractHitsTotal($responseData));
            }

            $termDocumentFrequencies[$term] = $documentFrequency;
        }

        return new QueryTermFrequencyResult($docCount, $termDocumentFrequencies);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function createBatcher(): MsearchProbeBatcherInterface
    {
        return new MsearchProbeBatcher($this->elasticaClient);
    }

    /**
     * Simple whitespace split — mirrors {@see \SprykerCommunity\Shared\SearchRanking\Intent\QueryWindowExtractor}'s
     * own tokenization convention, deliberately NOT a real analyzer round trip (an `_analyze` call would
     * itself work against the "reduce round trips" goal this rewrite exists for). See this class' own
     * docblock/interface for the known semantic trade-off this implies.
     *
     * @param string $searchString
     *
     * @return array<int, string>
     */
    protected function extractTerms(string $searchString): array
    {
        $tokens = preg_split('/\s+/', trim($searchString)) ?: [];
        $tokens = array_filter($tokens, static fn (string $token): bool => $token !== '');

        return array_values(array_unique($tokens));
    }

    /**
     * @param array<string, mixed>|null $responseData
     */
    protected function extractHitsTotal(?array $responseData): int
    {
        if ($responseData === null) {
            return 0;
        }

        $hits = is_array($responseData['hits'] ?? null) ? $responseData['hits'] : [];
        $total = is_array($hits['total'] ?? null) ? $hits['total'] : [];

        return (int)($total['value'] ?? 0);
    }

    /**
     * @param string $probeKeyPrefix
     */
    protected function buildDocCountKey(string $probeKeyPrefix): string
    {
        return $probeKeyPrefix . static::RESPONSE_KEY_DOC_COUNT_SUFFIX;
    }

    /**
     * @param string $probeKeyPrefix
     * @param string $field
     * @param string $term
     */
    protected function buildTermKey(string $probeKeyPrefix, string $field, string $term): string
    {
        return $probeKeyPrefix . static::RESPONSE_KEY_TERM_SEPARATOR . $field . ':' . $term;
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
