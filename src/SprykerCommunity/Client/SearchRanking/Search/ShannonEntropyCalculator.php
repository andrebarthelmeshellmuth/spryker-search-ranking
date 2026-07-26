<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

class ShannonEntropyCalculator implements ShannonEntropyCalculatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param array<float> $scores
     *
     * @return float
     */
    public function calculateNormalizedEntropy(array $scores): float
    {
        $candidateCount = count($scores);

        if ($candidateCount < 2) {
            return 0.0;
        }

        $scoreSum = array_sum($scores);

        if ($scoreSum <= 0.0) {
            return 0.0;
        }

        $entropy = 0.0;

        foreach ($scores as $score) {
            if ($score <= 0.0) {
                // p * log(p) is defined as 0 in the limit p → 0; skip to avoid log(0).
                continue;
            }

            $probability = $score / $scoreSum;
            $entropy -= $probability * log($probability);
        }

        return $entropy / log($candidateCount);
    }
}
