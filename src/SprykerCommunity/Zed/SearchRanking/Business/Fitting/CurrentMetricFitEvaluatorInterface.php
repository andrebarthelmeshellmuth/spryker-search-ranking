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
     */
    public function evaluate(int $idSearchRankingMetric, string $storeName, string $localeName): ?float;

    /**
     * Specification:
     * - Same fit check as {@see evaluate()}, run once per real locale of $storeName instead of a single
     *   given locale -- the diagnostic this package's own formula/shape being store-only (not yet
     *   locale-scoped) can't otherwise answer: does this metric's CURRENT store-wide formula actually fit
     *   EVERY locale's own real data comparably well, or does one locale's fit quietly lag the others?
     * - Keyed by locale name; a locale with no digest yet (or the metric not configured for this store at
     *   all) maps to null, same absence-is-neutral convention {@see evaluate()} already uses -- never
     *   thrown, never omitted from the map.
     * - Read-only, no side effect, safe to call as often as a caller likes -- same guarantee as evaluate().
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     *
     * @return array<string, float|null>
     */
    public function evaluateAcrossLocales(int $idSearchRankingMetric, string $storeName): array;
}
