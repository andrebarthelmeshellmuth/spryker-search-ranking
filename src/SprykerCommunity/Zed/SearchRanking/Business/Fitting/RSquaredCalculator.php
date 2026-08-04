<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Fitting;

/**
 * Standard R² = 1 - SS_res / SS_tot, extracted so both {@see NormalizationCurveFitter} (ranking closed-form
 * candidates against a digest) and the metric-history fit-quality snapshot (ranking a metric's own
 * CONFIGURED formula against its digest) compute it identically rather than maintaining two copies of the
 * same math.
 */
class RSquaredCalculator
{
    /**
     * Returns null (rather than NAN/INF) when the two arrays differ in length, or a predicted value is
     * non-finite anywhere — an out-of-domain input is simply reported as "no fit computable", never
     * allowed to poison a ranking or a stored snapshot with garbage.
     *
     * @param array<int, float> $actualValues
     * @param array<int, float> $predictedValues
     */
    public function calculate(array $actualValues, array $predictedValues): ?float
    {
        if (count($actualValues) === 0 || count($actualValues) !== count($predictedValues)) {
            return null;
        }

        $meanActual = array_sum($actualValues) / count($actualValues);
        $sumSquaredResiduals = 0.0;
        $sumSquaredTotal = 0.0;

        foreach ($actualValues as $index => $actual) {
            $predicted = $predictedValues[$index];

            if (!is_finite($predicted)) {
                return null;
            }

            $sumSquaredResiduals += ($actual - $predicted) ** 2;
            $sumSquaredTotal += ($actual - $meanActual) ** 2;
        }

        if ($sumSquaredTotal === 0.0) {
            return $sumSquaredResiduals === 0.0 ? 1.0 : 0.0;
        }

        return 1 - $sumSquaredResiduals / $sumSquaredTotal;
    }
}
