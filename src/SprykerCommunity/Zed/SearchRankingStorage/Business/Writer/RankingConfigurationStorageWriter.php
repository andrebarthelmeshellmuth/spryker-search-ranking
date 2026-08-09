<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Business\Writer;

use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToStoreFacadeInterface;
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
    protected const KEY_SPECIFICITY_BLEND_WEIGHT = 'specificity_blend_weight';

    /**
     * @var string
     */
    protected const KEY_SPECIFICITY_SATURATION_POINT = 'specificity_saturation_point';

    /**
     * @var string
     */
    protected const KEY_SPECIFICITY_CURVE_EXPONENT = 'specificity_curve_exponent';

    /**
     * @var string
     */
    protected const KEY_SPECIFICITY_WEIGHT_EXPONENT = 'specificity_weight_exponent';

    /**
     * @var string
     */
    protected const KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE = 'specificity_weight_shift_magnitude';

    /**
     * @var string
     */
    protected const KEY_RANDOM_METRIC_NAME = 'random_metric_name';

    /**
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeInterface $synchronizationFacade
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToStoreFacadeInterface $storeFacade
     */
    public function __construct(
        protected SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade,
        protected SearchRankingStorageEntityManagerInterface $entityManager,
        protected SearchRankingStorageToSynchronizationFacadeInterface $synchronizationFacade,
        protected SearchRankingStorageToStoreFacadeInterface $storeFacade,
    ) {
    }

    /**
     * Publishes one configuration document per (store, locale) — mirrors core's
     * `CategoryNodeStorageWriter` nested-loop shape (all stores outer, that store's own available
     * locales inner), never a cross-join against every globally available locale.
     */
    public function publishRankingConfiguration(): void
    {
        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            $storeName = $storeTransfer->getNameOrFail();

            foreach ($storeTransfer->getAvailableLocaleIsoCodes() as $localeName) {
                $this->publishRankingConfigurationForStoreAndLocale($storeName, $localeName);
            }
        }

        // With direct synchronization the behavior only BUFFERS the write; core flushes the buffer
        // solely on console termination, so a save inside a Zed web request (settings form, metric
        // CRUD) would never reach key-value storage without this explicit flush. Harmless when the
        // buffer is empty or in console context. One flush after all scopes, not per scope, matches
        // the existing single-flush-per-call contract this method already had.
        $this->synchronizationFacade->flushSynchronizationMessagesFromBuffer();
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    protected function publishRankingConfigurationForStoreAndLocale(string $storeName, string $localeName): void
    {
        $metricWeights = [];

        foreach ($this->searchRankingFacade->getActiveMetricCollection($storeName, $localeName)->getMetrics() as $metricTransfer) {
            $metricWeights[$metricTransfer->getNameOrFail()] = $metricTransfer->getWeightOrFail();
        }

        $this->entityManager->saveRankingConfiguration([
            static::KEY_METRIC_WEIGHTS => $this->normalizeMetricWeights($metricWeights),
            static::KEY_RELEVANCE_WEIGHT => $this->searchRankingFacade->getRelevanceWeight($storeName, $localeName),
            static::KEY_RELEVANCE_SATURATION_POINT => $this->searchRankingFacade->getRelevanceSaturationPoint($storeName, $localeName),
            static::KEY_SPECIFICITY_BLEND_WEIGHT => $this->searchRankingFacade->getSpecificityBlendWeight($storeName, $localeName),
            static::KEY_SPECIFICITY_SATURATION_POINT => $this->searchRankingFacade->getSpecificitySaturationPoint($storeName, $localeName),
            static::KEY_SPECIFICITY_CURVE_EXPONENT => $this->searchRankingFacade->getSpecificityCurveExponent($storeName, $localeName),
            static::KEY_SPECIFICITY_WEIGHT_EXPONENT => $this->searchRankingFacade->getSpecificityWeightExponent($storeName, $localeName),
            static::KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE => $this->searchRankingFacade->getSpecificityWeightShiftMagnitude($storeName, $localeName),
            static::KEY_RANDOM_METRIC_NAME => $this->searchRankingFacade->getRandomMetricName(),
        ], $storeName, $localeName);
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
