<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Metric;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

interface MetricWriterInterface
{
    /**
     * Writes the metric's identity fields: name/isHigherBetter (global), formula/isActive/shape
     * (store-scoped, for $storeName). See {@see saveMetricWeight()} for the metric's (store,
     * locale)-scoped weight. $localeName is used only as a lens (which digest to fit shape against /
     * snapshot in history), never persisted as part of the metric's scope.
     * $metricTransfer->getChangeSource() (one of SharedSearchRankingConfig::CHANGE_SOURCE_*) is recorded
     * on the resulting history row if one is written; null means CHANGE_SOURCE_MANUAL.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): SearchRankingMetricTransfer;

    /**
     * Writes a metric's weight for one (store, locale) and, if it actually changed, records a history
     * snapshot at that same scope, tagged with $changeSource.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     * @param string $changeSource One of {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig}::CHANGE_SOURCE_*.
     */
    public function saveMetricWeight(
        int $idSearchRankingMetric,
        string $storeName,
        string $localeName,
        float $weight,
        string $changeSource = SharedSearchRankingConfig::CHANGE_SOURCE_MANUAL,
    ): void;

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
