<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

class QuerySpecificityCalculator implements QuerySpecificityCalculatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param array<string, float> $idfByTerm
     * @param float $blendWeight
     */
    public function calculateRawSpecificity(array $idfByTerm, float $blendWeight): float
    {
        $idfValues = array_values($idfByTerm);
        $termCount = count($idfValues);

        if ($termCount === 0) {
            return 0.0;
        }

        if ($termCount === 1) {
            return $idfValues[0];
        }

        $maxIdf = max($idfValues);
        $harmonicMeanIdf = $this->calculateHarmonicMean($idfValues);

        return ($blendWeight * $maxIdf) + ((1 - $blendWeight) * $harmonicMeanIdf);
    }

    /**
     * {@inheritDoc}
     *
     * @param float $rawSpecificity
     * @param float $saturationPoint
     */
    public function normalize(float $rawSpecificity, float $saturationPoint): float
    {
        if ($rawSpecificity <= 0.0) {
            return 0.0;
        }

        return $rawSpecificity / ($rawSpecificity + $saturationPoint);
    }

    /**
     * A `0.0` anywhere in `$values` is the correct limiting case for the harmonic mean (its reciprocal
     * sum diverges), not an error to guard against — resolved directly to `0.0` rather than dividing by
     * zero.
     *
     * @param array<float> $values
     */
    protected function calculateHarmonicMean(array $values): float
    {
        foreach ($values as $value) {
            if ($value <= 0.0) {
                return 0.0;
            }
        }

        $reciprocalSum = 0.0;

        foreach ($values as $value) {
            $reciprocalSum += 1 / $value;
        }

        return count($values) / $reciprocalSum;
    }
}
