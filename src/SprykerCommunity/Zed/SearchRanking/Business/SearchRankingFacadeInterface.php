<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business;

use Generated\Shared\Transfer\ProductPageLoadTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;

interface SearchRankingFacadeInterface
{
    /**
     * Specification:
     * - Returns all ranking metric definitions ordered by name.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getMetricCollection(): SearchRankingMetricCollectionTransfer;

    /**
     * Specification:
     * - Returns all ACTIVE ranking metric definitions ordered by name.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getActiveMetricCollection(): SearchRankingMetricCollectionTransfer;

    /**
     * Specification:
     * - Returns the blend weight for the ranking formula — the share of the final score coming from
     *   normalized text relevance, with `(1 - relevanceWeight)` going to business signals; falls back to
     *   the module config default when no value was saved yet.
     *
     * @api
     *
     * @return float
     */
    public function getRelevanceWeight(): float;

    /**
     * Specification:
     * - Persists the relevance blend-weight setting.
     *
     * @api
     *
     * @param float $relevanceWeight
     *
     * @return void
     */
    public function saveRelevanceWeight(float $relevanceWeight): void;

    /**
     * Specification:
     * - Returns the relevance saturation point for the ranking formula — the raw Elasticsearch `_score`
     *   at which normalized text relevance reaches 0.5; falls back to the module config default when no
     *   value was saved yet.
     *
     * @api
     *
     * @return float
     */
    public function getRelevanceSaturationPoint(): float;

    /**
     * Specification:
     * - Persists the relevance saturation-point setting.
     *
     * @api
     *
     * @param float $relevanceSaturationPoint
     *
     * @return void
     */
    public function saveRelevanceSaturationPoint(float $relevanceSaturationPoint): void;

    /**
     * Specification:
     * - Returns the number of top-ranked candidates the entropy probe samples; falls back to the module
     *   config default when no value was saved yet. Only meaningful once entropy-aware relevance
     *   weighting is enabled at the code level (a Client-layer bundle config flag, off by default).
     *
     * @api
     *
     * @return int
     */
    public function getEntropyProbeResultSize(): int;

    /**
     * Specification:
     * - Persists the entropy probe result-size setting.
     *
     * @api
     *
     * @param int $entropyProbeResultSize
     *
     * @return void
     */
    public function saveEntropyProbeResultSize(int $entropyProbeResultSize): void;

    /**
     * Specification:
     * - Returns the exponent reshaping how sharply the entropy-derived shift ramps up; falls back to the
     *   module config default when no value was saved yet.
     *
     * @api
     *
     * @return float
     */
    public function getEntropyWeightExponent(): float;

    /**
     * Specification:
     * - Persists the entropy weight exponent setting.
     *
     * @api
     *
     * @param float $entropyWeightExponent
     *
     * @return void
     */
    public function saveEntropyWeightExponent(float $entropyWeightExponent): void;

    /**
     * Specification:
     * - Returns the maximum amount the entropy-derived value may shift `relevanceWeight` away from its
     *   configured baseline, in either direction; falls back to the module config default when no value
     *   was saved yet.
     *
     * @api
     *
     * @return float
     */
    public function getEntropyWeightShiftMagnitude(): float;

    /**
     * Specification:
     * - Persists the entropy weight shift-magnitude setting.
     *
     * @api
     *
     * @param float $entropyWeightShiftMagnitude
     *
     * @return void
     */
    public function saveEntropyWeightShiftMagnitude(float $entropyWeightShiftMagnitude): void;

    /**
     * Specification:
     * - Divides every ACTIVE metric's weight by the sum of all active weights, and persists the result
     *   into `spy_search_ranking_metric.weight` — the same normalization already forced on every publish
     *   (see `RankingConfigurationStorageWriter`), made visible in the Zed metric list on demand.
     * - Not a correctness requirement — the published/ranking-time weights are already normalized
     *   regardless of whether this was ever called. Purely a transparency action.
     * - Does nothing (no write) when there are no active metrics, all active weights are 0, or the
     *   active weights already sum to 1 — safe to call repeatedly.
     *
     * @api
     *
     * @return bool True when weights actually changed and were persisted; false when nothing needed to change.
     */
    public function normalizeActiveMetricWeights(): bool;

    /**
     * Specification:
     * - Returns the metric with the given id or null if it does not exist.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric): ?SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Returns the metric with the given name or null if it does not exist.
     *
     * @api
     *
     * @param string $name
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Persists the given metric; creates it when idSearchRankingMetric is empty, updates it otherwise.
     * - Validates the normalization formula and throws InvalidFormulaException when it does not evaluate.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Deletes the metric with the given id; its product-metric rows are removed by cascade.
     * - Does nothing when the metric does not exist.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return void
     */
    public function deleteMetric(int $idSearchRankingMetric): void;

    /**
     * Specification:
     * - Trial-evaluates the given normalization formula against sample variables.
     * - Returns a response transfer with isSuccess and, on failure, the evaluation error message.
     *
     * @api
     *
     * @param string $formula
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer
     */
    public function validateFormula(string $formula): SearchRankingFormulaValidationResponseTransfer;

    /**
     * Specification:
     * - Recalculates the normalized ]0;1] value of every product-metric row of every active metric.
     * - Evaluates the metric's formula per row with variables x (raw value), min, max, avg, count.
     * - Clamps results into ]0;1].
     * - Skips a metric entirely when its formula fails to evaluate and reports it in the result's errors.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer
     */
    public function normalizeProductMetricValues(): SearchRankingNormalizationResultTransfer;

    /**
     * Specification:
     * - Bulk-loads the normalized ]0;1] values of all active metrics for the products referenced by
     *   `ProductPageLoadTransfer.productAbstractIds`.
     * - Sets them on each payload transfer as a [metricName => normalizedValue] map (empty map when
     *   a product has no scores).
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ProductPageLoadTransfer $productPageLoadTransfer
     *
     * @return \Generated\Shared\Transfer\ProductPageLoadTransfer
     */
    public function expandProductPageLoadTransferWithScores(ProductPageLoadTransfer $productPageLoadTransfer): ProductPageLoadTransfer;

    /**
     * Specification:
     * - Triggers `Product.product_abstract.publish` events (in chunks) for every product abstract
     *   that has at least one normalized value of an active metric, so their search documents get
     *   re-exported with fresh scores.
     * - Returns the number of products for which events were triggered.
     *
     * @api
     *
     * @return int
     */
    public function publishScoredProductAbstracts(): int;

    /**
     * Specification:
     * - Recomputes the distribution digest (min/max/mean/median + a 101-point percentile backbone) of
     *   every ACTIVE metric from its current raw_value rows.
     * - A metric with no product-metric rows yet is skipped, not counted.
     *
     * @api
     *
     * @return int The number of metrics a digest was (re)computed for.
     */
    public function rebuildMetricDigests(): int;

    /**
     * Specification:
     * - Returns the given metric's distribution digest, or null when it has never been computed
     *   (no product-metric rows yet, or {@see rebuildMetricDigests()} has never run).
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer|null
     */
    public function findMetricDigest(int $idSearchRankingMetric): ?SearchRankingMetricDigestTransfer;

    /**
     * Specification:
     * - Evaluates $formula at every one of the metric's digest percentile x-values and returns the
     *   resulting points, the empirical-CDF reference line, and ranked closed-form curve-fit suggestions
     *   for the given direction ($isHigherBetter).
     * - Sets errorMessage instead of points when the metric has no digest yet, or when $formula fails to
     *   evaluate at any sampled point.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $formula
     * @param bool $isHigherBetter
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer
     */
    public function previewFormula(int $idSearchRankingMetric, string $formula, bool $isHigherBetter): SearchRankingFormulaPreviewTransfer;

    /**
     * Specification:
     * - Does nothing and returns false when the configured random-tie-breaker metric
     *   ({@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getRandomMetricName()}) does not
     *   exist or is not active.
     * - Otherwise re-normalizes every one of its product-metric rows (producing fresh values, since its
     *   formula is expected to ignore the raw value it's given) and republishes every scored product so
     *   the new values reach Elasticsearch, then returns true.
     * - Intended for a nightly cron, separate from and independent of {@see normalizeProductMetricValues()}'s
     *   hourly cadence, which deliberately skips this same metric.
     *
     * @api
     *
     * @return bool
     */
    public function randomizeRandomMetricIfActive(): bool;

    /**
     * Specification:
     * - Probes the live search engine's ACTUAL capabilities directly — never a version-string comparison,
     *   since OpenSearch and Elasticsearch report incompatible version identifiers under the same API
     *   surface — for a fixed set of constructs this package uses today or could use in a future phase.
     * - Read-only: fires `_validate/query` and a deliberately empty `_rank_eval` request, never writes.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer;

    /**
     * Specification:
     * - Returns whether entropy-aware relevance weighting is active
     *   ({@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig::isEntropyWeightingEnabled()}).
     * - Unlike every other method on this facade, this is NOT Zed-editable/persisted — it's a pure
     *   code-level project flag, read directly rather than routed through `SettingManager`, since there
     *   is nothing to read-modify-write here.
     *
     * @api
     *
     * @return bool
     */
    public function isEntropyWeightingEnabled(): bool;
}
