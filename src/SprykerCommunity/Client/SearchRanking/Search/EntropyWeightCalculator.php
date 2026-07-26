<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Elastica\Client;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\Kernel\Store;
use SprykerCommunity\Client\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use Throwable;

/**
 * Resolves the index name the same way
 * {@see \Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolver} does
 * (`<prefix>_<store>_<sourceIdentifier>`), without pulling in its Store-client bridge — this class already
 * runs inside a real Yves request, where {@see \Spryker\Shared\Kernel\Store::getInstance()} is always
 * available directly.
 */
class EntropyWeightCalculator implements EntropyWeightCalculatorInterface
{
    /**
     * @var \Elastica\Client
     */
    protected Client $elasticaClient;

    /**
     * @var \Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig
     */
    protected SearchElasticsearchConfig $searchElasticsearchConfig;

    /**
     * @var \SprykerCommunity\Client\SearchRanking\SearchRankingConfig
     */
    protected SearchRankingConfig $config;

    /**
     * @var \SprykerCommunity\Client\SearchRanking\Search\ShannonEntropyCalculatorInterface
     */
    protected ShannonEntropyCalculatorInterface $entropyCalculator;

    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     * @param \SprykerCommunity\Client\SearchRanking\SearchRankingConfig $config
     * @param \SprykerCommunity\Client\SearchRanking\Search\ShannonEntropyCalculatorInterface $entropyCalculator
     */
    public function __construct(
        Client $elasticaClient,
        SearchElasticsearchConfig $searchElasticsearchConfig,
        SearchRankingConfig $config,
        ShannonEntropyCalculatorInterface $entropyCalculator,
    ) {
        $this->elasticaClient = $elasticaClient;
        $this->searchElasticsearchConfig = $searchElasticsearchConfig;
        $this->config = $config;
        $this->entropyCalculator = $entropyCalculator;
    }

    /**
     * {@inheritDoc}
     *
     * @param \Elastica\Query\AbstractQuery $baseQuery
     * @param float $configuredRelevanceWeight
     *
     * @return float
     */
    public function calculateRelevanceWeight(AbstractQuery $baseQuery, float $configuredRelevanceWeight): float
    {
        $scores = $this->fetchProbeScores($baseQuery);

        if ($scores === []) {
            return $configuredRelevanceWeight;
        }

        $normalizedEntropy = $this->entropyCalculator->calculateNormalizedEntropy($scores);
        $businessWeight = $normalizedEntropy ** $this->config->getEntropyWeightExponent();

        return max(0.0, min(1.0, 1 - $businessWeight));
    }

    /**
     * A failing or empty probe must never break the real search it was fired alongside — caught here and
     * treated as "no usable signal", letting the caller fall back to the configured static weight.
     *
     * @param \Elastica\Query\AbstractQuery $baseQuery
     *
     * @return array<float>
     */
    protected function fetchProbeScores(AbstractQuery $baseQuery): array
    {
        try {
            $probeQuery = Query::create($baseQuery);
            $probeQuery->setSize($this->config->getEntropyProbeResultSize());
            $probeQuery->setSource(false);

            $resultSet = $this->elasticaClient->getIndex($this->resolveIndexName())->search($probeQuery);

            return array_map(
                static fn ($result) => $result->getScore(),
                $resultSet->getResults(),
            );
        } catch (Throwable $exception) {
            return [];
        }
    }

    /**
     * @return string
     */
    protected function resolveIndexName(): string
    {
        $indexParameters = [
            $this->searchElasticsearchConfig->getIndexPrefix(),
            Store::getInstance()->getStoreName(),
            SharedSearchRankingConfig::PAGE_SOURCE_IDENTIFIER,
        ];

        return mb_strtolower(implode('_', array_filter($indexParameters)));
    }
}
