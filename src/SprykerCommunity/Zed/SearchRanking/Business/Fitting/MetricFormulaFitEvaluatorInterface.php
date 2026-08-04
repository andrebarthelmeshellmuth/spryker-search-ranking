<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Fitting;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;

interface MetricFormulaFitEvaluatorInterface
{
    /**
     * Specification:
     * - Evaluates $formula at every one of the digest's percentile x-values (min, max, avg, count taken
     *   from that same digest) and returns its R² against the empirical CDF — i.e. how well this EXACT,
     *   already-configured formula (not a candidate being searched for) currently fits the data.
     * - Returns null when $formula fails to evaluate at any sampled point, or produces a non-finite
     *   result — "no fit computable" is reported honestly rather than a misleading number.
     *
     * @param string $formula
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     */
    public function evaluateFit(string $formula, SearchRankingMetricDigestTransfer $digestTransfer): ?float;
}
