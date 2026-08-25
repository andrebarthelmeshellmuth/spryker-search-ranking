<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Elastica\Client;
use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilder;
use SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToLocaleClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToPermissionClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\RandomImpact\RandomImpactCalculator;
use SprykerCommunity\Client\SearchRanking\RandomImpact\RandomImpactCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityChecker;
use SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityCheckerInterface;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculator;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcher;
use SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingClientInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\SemanticQueryEmbeddingCache;
use SprykerCommunity\Client\SearchRanking\Semantic\SemanticQueryEmbeddingCacheInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\TextEmbeddingsInferenceClient;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingFactory extends AbstractFactory
{
    public function createFunctionScoreBuilder(): FunctionScoreBuilderInterface
    {
        return new FunctionScoreBuilder();
    }

    public function createRandomImpactCalculator(): RandomImpactCalculatorInterface
    {
        return new RandomImpactCalculator();
    }

    public function createScoreSectionBuilder(): ScoreSectionBuilderInterface
    {
        return new ScoreSectionBuilder();
    }

    public function getSearchRankingStorageClient(): SearchRankingToSearchRankingStorageClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_SEARCH_RANKING_STORAGE);
    }

    public function createEngineCompatibilityChecker(): EngineCompatibilityCheckerInterface
    {
        return new EngineCompatibilityChecker($this->getElasticaClient());
    }

    public function createSpecificityWeightCalculator(): SpecificityWeightCalculatorInterface
    {
        return new SpecificityWeightCalculator(
            $this->createQueryTermFrequencyFetcher(),
            $this->createQuerySpecificityCalculator(),
            $this->getConfig()->getSpecificityProbeFieldSearchAnalyzers(),
        );
    }

    public function getStoreClient(): SearchRankingToStoreClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_STORE);
    }

    public function getLocaleClient(): SearchRankingToLocaleClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_LOCALE);
    }

    public function getPermissionClient(): SearchRankingToPermissionClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_PERMISSION);
    }

    public function createQueryTermFrequencyFetcher(): QueryTermFrequencyFetcherInterface
    {
        return new QueryTermFrequencyFetcher(
            $this->getElasticaClient(),
            $this->createSearchElasticsearchConfig(),
            $this->getStoreClient(),
        );
    }

    public function createQuerySpecificityCalculator(): QuerySpecificityCalculatorInterface
    {
        return new QuerySpecificityCalculator();
    }

    /**
     * COMPOSITION over the core SearchElasticsearch module, deliberately — same pattern (and the same
     * reasoning) as `SprykerCommunity\Client\SearchDebug\SearchDebugFactory::getElasticaClient()`.
     */
    public function getElasticaClient(): Client
    {
        return $this->createElasticaClientFactory()->createClient(
            $this->createSearchElasticsearchConfig()->getClientConfig(),
        );
    }

    public function createElasticaClientFactory(): ElasticaClientFactory
    {
        return new ElasticaClientFactory();
    }

    public function createSearchElasticsearchConfig(): SearchElasticsearchConfig
    {
        return new SearchElasticsearchConfig();
    }

    public function createEmbeddingClient(): EmbeddingClientInterface
    {
        return new TextEmbeddingsInferenceClient($this->getConfig()->getEmbeddingServiceUrl());
    }

    public function createSemanticQueryEmbeddingCache(): SemanticQueryEmbeddingCacheInterface
    {
        return new SemanticQueryEmbeddingCache($this->getStorageClient());
    }

    public function getStorageClient(): SearchRankingToStorageClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_STORAGE);
    }
}
