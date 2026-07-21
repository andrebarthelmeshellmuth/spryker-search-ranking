<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Fitting;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;

interface NormalizationCurveFitterInterface
{
    /**
     * Fits a small palette of closed-form, single-parameter normalization curves against the digest's
     * empirical CDF (percentiles[i] is the raw value below which i% of products fall, so the fit target
     * at each point is simply i/100) and ranks them by R² (1.0 = perfect fit). Returns them best-first,
     * with the best CLOSED-FORM candidate flagged isWinner — the empirical CDF itself is not among the
     * candidates (it fits perfectly by construction, which would make it win trivially and unhelpfully;
     * it is exposed separately as the reference line on every preview plot instead).
     *
     * $isHigherBetter selects the candidate shapes: increasing/saturating curves when true (a bigger raw
     * value is the better outcome), decreasing/decay curves when false.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     * @param bool $isHigherBetter
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer>
     */
    public function fit(SearchRankingMetricDigestTransfer $digestTransfer, bool $isHigherBetter): array;
}
