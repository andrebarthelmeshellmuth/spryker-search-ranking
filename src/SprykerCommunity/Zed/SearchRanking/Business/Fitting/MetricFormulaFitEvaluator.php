<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Fitting;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\FormulaEvaluationException;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;

/**
 * Answers "how well does the metric's OWN CONFIGURED formula fit the data right now" — as opposed to
 * {@see NormalizationCurveFitter}, which answers "what closed-form shape would fit best". Both ultimately
 * measure the same thing (R² against the empirical CDF) via the shared {@see RSquaredCalculator}, just
 * for a caller-given formula string instead of a searched candidate parameter.
 *
 * Used to populate the fit-quality snapshot on {@see \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManager::saveMetric()}
 * and, eventually, by a periodic drift-detection job deciding whether a metric's formula still fits well
 * enough to leave alone.
 */
class MetricFormulaFitEvaluator implements MetricFormulaFitEvaluatorInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface $formulaEvaluator
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Fitting\RSquaredCalculator $rSquaredCalculator
     */
    public function __construct(protected FormulaEvaluatorInterface $formulaEvaluator, protected RSquaredCalculator $rSquaredCalculator)
    {
    }

    /**
     * @param string $formula
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     */
    public function evaluateFit(string $formula, SearchRankingMetricDigestTransfer $digestTransfer): ?float
    {
        $baseVariables = [
            'min' => $digestTransfer->getMinValueOrFail(),
            'max' => $digestTransfer->getMaxValueOrFail(),
            'avg' => $digestTransfer->getMeanValueOrFail(),
            'count' => $digestTransfer->getSampleCountOrFail(),
        ];

        $percentiles = $digestTransfer->getPercentiles();
        $lastIndex = count($percentiles) - 1;

        if ($lastIndex <= 0) {
            return null;
        }

        $actualValues = [];
        $predictedValues = [];

        foreach ($percentiles as $index => $x) {
            $actualValues[] = $index / $lastIndex;

            try {
                $predictedValues[] = $this->formulaEvaluator->evaluate($formula, $baseVariables + ['x' => $x]);
            } catch (FormulaEvaluationException) {
                return null;
            }
        }

        return $this->rSquaredCalculator->calculate($actualValues, $predictedValues);
    }
}
