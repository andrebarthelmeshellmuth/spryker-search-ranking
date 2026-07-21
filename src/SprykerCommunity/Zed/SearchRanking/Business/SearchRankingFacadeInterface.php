<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business;

use Generated\Shared\Transfer\ProductPageLoadTransfer;
use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
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
     * - Parses $csvContent (one search term per line) and creates a new calibration run in
     *   status=uploaded, with one child row per parsed, deduplicated search term.
     * - Fires no search queries — {@see runNextCalibration()} does that, on its own schedule.
     *
     * @api
     *
     * @param int $relevantProductCount
     * @param string $storeName
     * @param string $localeName
     * @param string $csvContent
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer
     */
    public function createCalibration(int $relevantProductCount, string $storeName, string $localeName, string $csvContent): SearchRankingCalibrationTransfer;

    /**
     * Specification:
     * - Picks the newest calibration run in status=uploaded (if any), marks every OTHER uploaded run as
     *   status=skipped, then fires the real, fully-wired catalog search-string query for each of its
     *   search terms and pools the resulting raw text-relevance scores into the run's statistics.
     * - Sets status=calculated on success, status=failed (with errorMessage) when not a single score
     *   could be collected.
     * - Returns null when there was no uploaded run to process.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer|null
     */
    public function runNextCalibration(): ?SearchRankingCalibrationTransfer;

    /**
     * Specification:
     * - Returns the most recently finished (status=calculated) calibration run, or null when none has
     *   ever finished.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer|null
     */
    public function findLatestCalculatedCalibration(): ?SearchRankingCalibrationTransfer;

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
}
