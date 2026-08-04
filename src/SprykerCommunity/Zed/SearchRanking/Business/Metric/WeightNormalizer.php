<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Metric;

use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Materializes into `spy_search_ranking_metric.weight` the same forced normalization
 * `RankingConfigurationStorageWriter::normalizeMetricWeights()` (Client/SearchRankingStorage) already
 * applies silently at every publish — that forced normalization alone is enough to guarantee the
 * business-signal term stays in `[0;1]` (see this package's README, "Ranking formula"), but it means
 * the Zed metric list's weight column can show numbers that don't match what's actually used, whenever
 * active weights don't already sum to 1. This class lets an admin make the visible weights and the
 * effective weights the same thing, on demand — a transparency action, not a correctness requirement:
 * ranking is already correct without it ever being clicked.
 */
class WeightNormalizer implements WeightNormalizerInterface
{
    /**
     * Below this, the sum of active weights is treated as "already 1" — normalizing again would only
     * rewrite each weight to a value indistinguishable from what it already is.
     *
     * @var float
     */
    protected const ALREADY_NORMALIZED_EPSILON = 1.0E-9;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface $metricWriter
     */
    public function __construct(protected SearchRankingRepositoryInterface $repository, protected MetricWriterInterface $metricWriter)
    {
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return bool
     *   True when any weight actually changed (and was persisted); false when there were no active
     *   metrics, all active weights were 0, or the active weights already summed to 1 — in every one of
     *   those cases nothing is written, so pressing this repeatedly is a genuine no-op once weights are
     *   normalized, not just a fresh write of the same numbers.
     */
    public function normalizeActiveWeights(string $storeName, string $localeName): bool
    {
        $metricTransfers = $this->repository->getActiveMetricCollection($storeName, $localeName)->getMetrics();

        $weightSum = 0.0;

        foreach ($metricTransfers as $metricTransfer) {
            $weightSum += $metricTransfer->getWeightOrFail();
        }

        if ($weightSum === 0.0 || abs($weightSum - 1.0) < static::ALREADY_NORMALIZED_EPSILON) {
            return false;
        }

        foreach ($metricTransfers as $metricTransfer) {
            $this->metricWriter->saveMetricWeight(
                $metricTransfer->getIdSearchRankingMetricOrFail(),
                $storeName,
                $localeName,
                $metricTransfer->getWeightOrFail() / $weightSum,
            );
        }

        return true;
    }
}
