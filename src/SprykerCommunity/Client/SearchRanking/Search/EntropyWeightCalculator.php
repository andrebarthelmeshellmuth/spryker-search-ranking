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
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\Kernel\Store;
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
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     * @param \SprykerCommunity\Client\SearchRanking\Search\ShannonEntropyCalculatorInterface $entropyCalculator
     */
    public function __construct(
        protected Client $elasticaClient,
        protected SearchElasticsearchConfig $searchElasticsearchConfig,
        protected ShannonEntropyCalculatorInterface $entropyCalculator,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param \Elastica\Query\AbstractQuery $baseQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return float
     */
    public function calculateRelevanceWeight(
        AbstractQuery $baseQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): float {
        return $this->calculateWeightingResult($baseQuery, $configurationTransfer)->getRelevanceWeight();
    }

    /**
     * {@inheritDoc}
     *
     * @param \Elastica\Query\AbstractQuery $baseQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult
     */
    public function calculateWeightingResult(
        AbstractQuery $baseQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): EntropyWeightingResult {
        $configuredRelevanceWeight = (float)$configurationTransfer->getRelevanceWeight();
        $scores = $this->fetchProbeScores($baseQuery, (int)$configurationTransfer->getEntropyProbeResultSize());

        if ($scores === []) {
            return new EntropyWeightingResult($configuredRelevanceWeight, $configuredRelevanceWeight, 0.0, 0.0, 0);
        }

        $normalizedEntropy = $this->entropyCalculator->calculateNormalizedEntropy($scores);
        $shift = $this->calculateShift(
            $normalizedEntropy,
            (float)$configurationTransfer->getEntropyWeightExponent(),
            (float)$configurationTransfer->getEntropyWeightShiftMagnitude(),
        );
        $relevanceWeight = max(0.0, min(1.0, $configuredRelevanceWeight + $shift));

        return new EntropyWeightingResult(
            $configuredRelevanceWeight,
            $relevanceWeight,
            $normalizedEntropy,
            $shift,
            count($scores),
        );
    }

    /**
     * `1 - 2 * normalizedEntropy` maps entropy's `[0;1]` range onto a signed `[-1;1]` deviation from the
     * neutral point (entropy exactly 0.5 → deviation 0): positive when one score dominates (shift toward
     * text relevance), negative when the distribution is flat (shift toward business signals). The
     * exponent is applied to the deviation's MAGNITUDE only, sign preserved separately — applying it to
     * `normalizedEntropy` directly (before centering) would move the neutral point away from 0.5 whenever
     * the exponent isn't exactly 1.0, which is not what the exponent is meant to control.
     *
     * @param float $normalizedEntropy
     * @param float $exponent
     * @param float $shiftMagnitude
     *
     * @return float
     */
    protected function calculateShift(float $normalizedEntropy, float $exponent, float $shiftMagnitude): float
    {
        $signedDeviation = 1 - (2 * $normalizedEntropy);
        $shapedDeviation = ($signedDeviation <=> 0) * (abs($signedDeviation) ** $exponent);

        return $shiftMagnitude * $shapedDeviation;
    }

    /**
     * A failing or empty probe must never break the real search it was fired alongside — caught here and
     * treated as "no usable signal", letting the caller fall back to the configured static weight.
     *
     * @param \Elastica\Query\AbstractQuery $baseQuery
     * @param int $probeResultSize
     *
     * @return array<float>
     */
    protected function fetchProbeScores(AbstractQuery $baseQuery, int $probeResultSize): array
    {
        try {
            $probeQuery = Query::create($baseQuery);
            $probeQuery->setSize($probeResultSize);
            $probeQuery->setSource(false);

            $resultSet = $this->elasticaClient->getIndex($this->resolveIndexName())->search($probeQuery);

            return array_map(
                static fn ($result) => $result->getScore(),
                $resultSet->getResults(),
            );
        } catch (Throwable) {
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
