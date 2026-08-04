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
     * Writes only the metric's IDENTITY fields (name/formula/isActive/isHigherBetter/shape) — global,
     * not store/locale scoped. See {@see saveMetricWeight()} for the metric's (store, locale)-scoped
     * weight.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer;

    /**
     * Writes a metric's weight for one (store, locale) and, if it actually changed, records a history
     * snapshot at that same scope.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     */
    public function saveMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName, float $weight): void;

    /**
     * @param int $idSearchRankingMetric
     */
    public function deleteMetric(int $idSearchRankingMetric): void;

    /**
     * Appends an `isChange=false` history row for $metricTransfer's CURRENT (unmodified) config, weight
     * (at the given store+locale), and digest — used by the auto-tune job when a metric's fit was
     * checked but no update was applied (either because the fit is still adequate, or because
     * auto-update is switched off), so the history/audit timeline stays complete even on runs that
     * change nothing. Never call this after an actual config change — {@see saveMetric()} /
     * {@see saveMetricWeight()} already record an `isChange=true` row for that themselves.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function recordCheckOnly(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): void;
}
