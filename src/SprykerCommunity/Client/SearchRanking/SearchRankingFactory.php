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
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityChecker;
use SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityCheckerInterface;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightCalculator;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Search\ShannonEntropyCalculator;
use SprykerCommunity\Client\SearchRanking\Search\ShannonEntropyCalculatorInterface;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingFactory extends AbstractFactory
{
    /**
     * @return \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface
     */
    public function createFunctionScoreBuilder(): FunctionScoreBuilderInterface
    {
        return new FunctionScoreBuilder();
    }

    /**
     * @return \SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilderInterface
     */
    public function createScoreSectionBuilder(): ScoreSectionBuilderInterface
    {
        return new ScoreSectionBuilder();
    }

    /**
     * @return \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface
     */
    public function getSearchRankingStorageClient(): SearchRankingToSearchRankingStorageClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_SEARCH_RANKING_STORAGE);
    }

    /**
     * @return \SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityCheckerInterface
     */
    public function createEngineCompatibilityChecker(): EngineCompatibilityCheckerInterface
    {
        return new EngineCompatibilityChecker($this->getElasticaClient());
    }

    /**
     * @return \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightCalculatorInterface
     */
    public function createEntropyWeightCalculator(): EntropyWeightCalculatorInterface
    {
        return new EntropyWeightCalculator(
            $this->getElasticaClient(),
            $this->createSearchElasticsearchConfig(),
            $this->getConfig(),
            $this->createShannonEntropyCalculator(),
        );
    }

    /**
     * @return \SprykerCommunity\Client\SearchRanking\Search\ShannonEntropyCalculatorInterface
     */
    public function createShannonEntropyCalculator(): ShannonEntropyCalculatorInterface
    {
        return new ShannonEntropyCalculator();
    }

    /**
     * COMPOSITION over the core SearchElasticsearch module, deliberately — same pattern (and the same
     * reasoning) as `SprykerCommunity\Client\SearchDebug\SearchDebugFactory::getElasticaClient()`.
     *
     * @return \Elastica\Client
     */
    public function getElasticaClient(): Client
    {
        return $this->createElasticaClientFactory()->createClient(
            $this->createSearchElasticsearchConfig()->getClientConfig(),
        );
    }

    /**
     * @return \Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory
     */
    public function createElasticaClientFactory(): ElasticaClientFactory
    {
        return new ElasticaClientFactory();
    }

    /**
     * @return \Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig
     */
    public function createSearchElasticsearchConfig(): SearchElasticsearchConfig
    {
        return new SearchElasticsearchConfig();
    }
}
