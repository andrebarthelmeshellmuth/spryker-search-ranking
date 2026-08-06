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
     * @param float $curveExponent
     */
    public function normalize(float $rawSpecificity, float $saturationPoint, float $curveExponent = 1.0): float
    {
        if ($rawSpecificity <= 0.0) {
            return 0.0;
        }

        if ($curveExponent === 1.0) {
            // The plain, un-shaped curve — skips ** entirely, both as a fast path (this is the default,
            // the overwhelming majority of calls) and so this exact case can never drift from the
            // original formula's own floating-point behavior no matter how ** is implemented.
            return $rawSpecificity / ($rawSpecificity + $saturationPoint);
        }

        $shapedRawSpecificity = $rawSpecificity ** $curveExponent;
        $shapedSaturationPoint = $saturationPoint ** $curveExponent;

        return $shapedRawSpecificity / ($shapedRawSpecificity + $shapedSaturationPoint);
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
