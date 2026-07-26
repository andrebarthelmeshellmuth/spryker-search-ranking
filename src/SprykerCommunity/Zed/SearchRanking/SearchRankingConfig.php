<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking;

use Spryker\Zed\Kernel\AbstractBundleConfig;

class SearchRankingConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Number of product-metric rows the normalization processes per batch.
     *
     * @api
     *
     * @return int
     */
    public function getNormalizationBatchSize(): int
    {
        return 1000;
    }

    /**
     * Specification:
     * - Lower clamp bound for normalized values. Normalized values must lie in ]0;1], so results
     *   of 0 or below are raised to this minimum, results above 1 are capped at 1.
     *
     * @api
     *
     * @return float
     */
    public function getNormalizedValueMinimum(): float
    {
        return 1.0E-6;
    }

    /**
     * Specification:
     * - Default blend weight (share of the final score coming from normalized text relevance, vs.
     *   `(1 - relevanceWeight)` going to business signals) when none was saved in Zed yet. 0.5 is a
     *   neutral starting split with no prior favoring either side.
     *
     * @api
     *
     * @return float
     */
    public function getDefaultRelevanceWeight(): float
    {
        return 0.5;
    }

    /**
     * Specification:
     * - Default relevance saturation point (the raw Elasticsearch `_score` at which normalized text
     *   relevance reaches 0.5) when none was saved in Zed yet. Chosen from real `_score` values observed
     *   for this demoshop's own catalog/queries (single/double-term searches typically scored roughly
     *   4-20) — a starting point to tune from, not a derived constant, since it depends entirely on this
     *   shop's own field boosts and query patterns.
     *
     * @api
     *
     * @return float
     */
    public function getDefaultRelevanceSaturationPoint(): float
    {
        return 12.0;
    }

    /**
     * Specification:
     * - Default number of top-ranked candidates the entropy probe samples when none was saved in Zed
     *   yet. Only meaningful once entropy-aware relevance weighting is enabled at the code level.
     *
     * @api
     *
     * @return int
     */
    public function getDefaultEntropyProbeResultSize(): int
    {
        return 10;
    }

    /**
     * Specification:
     * - Default exponent reshaping how sharply the entropy-derived shift ramps up, when none was saved
     *   in Zed yet. `1.0` applies the shift linearly with no reshaping.
     *
     * @api
     *
     * @return float
     */
    public function getDefaultEntropyWeightExponent(): float
    {
        return 1.0;
    }

    /**
     * Specification:
     * - Default maximum amount the entropy-derived value may shift `relevanceWeight` away from its
     *   configured baseline, in either direction, when none was saved in Zed yet. `0.5` lets a baseline
     *   of exactly 0.5 reach the full `[0;1]` range; baselines closer to either edge reach
     *   correspondingly less on the side nearer that edge — an unavoidable consequence of `relevanceWeight`
     *   itself being bounded to `[0;1]`, not a flaw in the shift formula.
     *
     * @api
     *
     * @return float
     */
    public function getDefaultEntropyWeightShiftMagnitude(): float
    {
        return 0.5;
    }

    /**
     * Specification:
     * - Number of product abstract publish events triggered per bulk when re-publishing scored products.
     *
     * @api
     *
     * @return int
     */
    public function getPublishEventChunkSize(): int
    {
        return 500;
    }

    /**
     * Specification:
     * - Sample variables used to trial-evaluate a formula during validation.
     *
     * @api
     *
     * @return array<string, float|int>
     */
    public function getFormulaValidationVariables(): array
    {
        return [
            'x' => 1.0,
            'min' => 0.0,
            'max' => 1.0,
            'avg' => 0.5,
            'count' => 10,
        ];
    }

    /**
     * Specification:
     * - Name of the metric treated as the random tie-breaker signal.
     * - `ProductMetricNormalizer::normalize()` (the hourly cron) deliberately skips this metric — it is
     *   refreshed on its own nightly cadence instead, by `search-ranking:randomize`
     *   (`MetricRandomizer::randomizeIfActive()`). Reshuffling a tie-breaker every hour would make search
     *   result order visibly churn for a shopper who searches again shortly after; nightly is frequent
     *   enough to keep ties from calcifying into a permanent order without looking unstable.
     * - The metric itself is not otherwise special-cased: its formula is expected to be `random()`, but
     *   nothing here enforces that — a project could name a different metric here, with a different
     *   formula, and get the same "own nightly cadence, skipped by the hourly loop" treatment.
     *
     * @api
     *
     * @return string
     */
    public function getRandomMetricName(): string
    {
        return 'random';
    }
}
