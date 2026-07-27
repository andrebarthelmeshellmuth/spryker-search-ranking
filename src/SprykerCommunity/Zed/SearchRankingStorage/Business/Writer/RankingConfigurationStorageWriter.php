<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Business\Writer;

use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface;

/**
 * Normalizes active metric weights to sum to 1 before publishing, so `Σ weightᵢ × signalᵢ` in the
 * function_score script is guaranteed to stay in `[0;1]` — a weighted average of values already in
 * `]0;1]` (each metric's own normalized signal) cannot leave that interval once its own weights sum to
 * 1. Without this, the Zed metric form only constrains a single weight to be `>= 0` (see MetricForm) —
 * nothing stops an admin from entering e.g. 1.0 for three active metrics at once, which would let the
 * business-signal term reach 3 and swamp the `relevanceWeight` blend the whole formula depends on being
 * `[0;1]` on both sides. See this package's README ("Ranking formula") for the full rationale.
 */
class RankingConfigurationStorageWriter implements RankingConfigurationStorageWriterInterface
{
    /**
     * @var string
     */
    protected const KEY_METRIC_WEIGHTS = 'metric_weights';

    /**
     * @var string
     */
    protected const KEY_RELEVANCE_WEIGHT = 'relevance_weight';

    /**
     * @var string
     */
    protected const KEY_RELEVANCE_SATURATION_POINT = 'relevance_saturation_point';

    /**
     * @var string
     */
    protected const KEY_ENTROPY_PROBE_RESULT_SIZE = 'entropy_probe_result_size';

    /**
     * @var string
     */
    protected const KEY_ENTROPY_WEIGHT_EXPONENT = 'entropy_weight_exponent';

    /**
     * @var string
     */
    protected const KEY_ENTROPY_WEIGHT_SHIFT_MAGNITUDE = 'entropy_weight_shift_magnitude';

    /**
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeInterface $synchronizationFacade
     */
    public function __construct(
        protected SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade,
        protected SearchRankingStorageEntityManagerInterface $entityManager,
        protected SearchRankingStorageToSynchronizationFacadeInterface $synchronizationFacade,
    ) {
    }

    /**
     * @return void
     */
    public function publishRankingConfiguration(): void
    {
        $metricWeights = [];

        foreach ($this->searchRankingFacade->getActiveMetricCollection()->getMetrics() as $metricTransfer) {
            $metricWeights[$metricTransfer->getNameOrFail()] = $metricTransfer->getWeightOrFail();
        }

        $this->entityManager->saveRankingConfiguration([
            static::KEY_METRIC_WEIGHTS => $this->normalizeMetricWeights($metricWeights),
            static::KEY_RELEVANCE_WEIGHT => $this->searchRankingFacade->getRelevanceWeight(),
            static::KEY_RELEVANCE_SATURATION_POINT => $this->searchRankingFacade->getRelevanceSaturationPoint(),
            static::KEY_ENTROPY_PROBE_RESULT_SIZE => $this->searchRankingFacade->getEntropyProbeResultSize(),
            static::KEY_ENTROPY_WEIGHT_EXPONENT => $this->searchRankingFacade->getEntropyWeightExponent(),
            static::KEY_ENTROPY_WEIGHT_SHIFT_MAGNITUDE => $this->searchRankingFacade->getEntropyWeightShiftMagnitude(),
        ]);

        // With direct synchronization the behavior only BUFFERS the write; core flushes the buffer
        // solely on console termination, so a save inside a Zed web request (settings form, metric
        // CRUD) would never reach key-value storage without this explicit flush. Harmless when the
        // buffer is empty or in console context.
        $this->synchronizationFacade->flushSynchronizationMessagesFromBuffer();
    }

    /**
     * Divides each weight by the sum of all of them, so the published weights always sum to exactly 1
     * — the raw admin-entered values in `spy_search_ranking_metric.weight` are untouched, only this
     * derived/published copy is normalized (same relationship the raw→normalized product-metric values
     * already have). With a single active metric, its weight normalizes to 1 regardless of the raw
     * number entered — it's 100% of the active-weight sum either way.
     *
     * @param array<string, float> $metricWeights
     *
     * @return array<string, float>
     */
    protected function normalizeMetricWeights(array $metricWeights): array
    {
        // array_sum() returns int(0), not float(0.0), for an empty array — cast explicitly so the
        // strict comparison below actually catches that case too, not just "all weights are 0.0".
        $weightSum = (float)array_sum($metricWeights);

        // No active metric has a non-zero weight (including zero active metrics at all) — leave the
        // weights as-is (all zero, or empty) rather than dividing by zero. FunctionScoreBuilder already
        // treats an all-zero/empty weight map as "no usable business signal" and steps aside to pure
        // text relevance, so this degrades exactly the same way it already did before normalization
        // existed.
        if ($weightSum === 0.0) {
            return $metricWeights;
        }

        return array_map(
            fn (float $weight): float => $weight / $weightSum,
            $metricWeights,
        );
    }
}
