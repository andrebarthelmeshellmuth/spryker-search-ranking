<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Metric;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;

interface MetricWriterInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer;

    /**
     * @param int $idSearchRankingMetric
     *
     * @return void
     */
    public function deleteMetric(int $idSearchRankingMetric): void;

    /**
     * Appends an `isChange=false` history row for $metricTransfer's CURRENT (unmodified) config and digest
     * — used by the auto-tune job when a metric's fit was checked but no update was applied (either
     * because the fit is still adequate, or because auto-update is switched off), so the history/audit
     * timeline stays complete even on runs that change nothing. Never call this after an actual
     * config change — {@see saveMetric()} already records an `isChange=true` row for that itself.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return void
     */
    public function recordCheckOnly(SearchRankingMetricTransfer $metricTransfer): void;
}
