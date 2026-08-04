<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Fitting;

interface CurrentMetricFitEvaluatorInterface
{
    /**
     * Specification:
     * - Answers "how well does $idSearchRankingMetric's OWN CONFIGURED formula fit its data RIGHT NOW",
     *   as a fresh read with no side effect — unlike {@see \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface::recordCheckOnly()},
     *   this never writes a history row, so it is safe to call as often as a caller likes (e.g. a
     *   drift-detection job probing every metric before deciding which ones actually crossed a threshold).
     * - Returns null when the metric doesn't exist, or has no digest yet (nothing to fit against).
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return float|null
     */
    public function evaluate(int $idSearchRankingMetric, string $storeName, string $localeName): ?float;
}
