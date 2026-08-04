<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Preview;

use Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer;

interface FormulaPreviewBuilderInterface
{
    /**
     * Specification:
     * - Evaluates $formula at every one of the metric's digest percentile x-values (min, max, avg, count
     *   taken from that same digest) and returns the resulting (x, y) points, alongside the empirical-CDF
     *   reference line and the ranked curve-fit suggestions for the metric's direction.
     * - Sets errorMessage instead of points when the metric has no digest yet, or when $formula fails to
     *   evaluate at any sampled point — never a partial/mixed result.
     *
     * @param int $idSearchRankingMetric
     * @param string $formula
     * @param bool $isHigherBetter
     * @param string $storeName
     * @param string $localeName
     */
    public function buildPreview(
        int $idSearchRankingMetric,
        string $formula,
        bool $isHigherBetter,
        string $storeName,
        string $localeName,
    ): SearchRankingFormulaPreviewTransfer;
}
