<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\RandomImpact;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

class RandomImpactCalculator implements RandomImpactCalculatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     */
    public function isActive(SearchRankingConfigurationStorageTransfer $configurationTransfer): bool
    {
        $randomMetricName = $configurationTransfer->getRandomMetricName();

        if ($randomMetricName === null || $randomMetricName === '') {
            return false;
        }

        $weight = (float)($configurationTransfer->getMetricWeights()[$randomMetricName] ?? 0.0);

        return $weight > 0.0;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<int, array{idProductAbstract: int, score: float, randomSignal: float}> $hits
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return array<int, int>
     */
    public function calculate(array $hits, SearchRankingConfigurationStorageTransfer $configurationTransfer): array
    {
        // A single hit (or none) has no other position to move relative to -- and isActive() already
        // guards the "nothing to simulate at all" case (no random weight configured).
        if (count($hits) < 2 || !$this->isActive($configurationTransfer)) {
            return [];
        }

        $randomWeight = (float)$configurationTransfer->getMetricWeights()[$configurationTransfer->getRandomMetricNameOrFail()];
        $businessSignalShare = 1.0 - (float)$configurationTransfer->getRelevanceWeight();

        $simulatedScoresByIndex = [];

        foreach (array_values($hits) as $index => $hit) {
            $simulatedScoresByIndex[$index] = $hit['score'] - $businessSignalShare * $randomWeight * $hit['randomSignal'];
        }

        // Stable since PHP 8.0 -- hits with an identical simulated score keep their original relative
        // (live) order, the same tie-breaking a shopper would have seen anyway.
        arsort($simulatedScoresByIndex);

        $simulatedPositionByIndex = [];
        $position = 1;

        foreach (array_keys($simulatedScoresByIndex) as $index) {
            $simulatedPositionByIndex[$index] = $position;
            $position++;
        }

        $deltasByIdProductAbstract = [];

        foreach (array_values($hits) as $index => $hit) {
            $delta = $simulatedPositionByIndex[$index] - ($index + 1);

            if ($delta === 0) {
                continue;
            }

            $deltasByIdProductAbstract[$hit['idProductAbstract']] = $delta;
        }

        return $deltasByIdProductAbstract;
    }
}
