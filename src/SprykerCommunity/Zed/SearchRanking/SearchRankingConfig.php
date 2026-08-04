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
     */
    public function getNormalizedValueMinimum(): float
    {
        return 1.0E-6;
    }

    /**
     * Specification:
     * - Default blend weight (share of the final score coming from normalized text relevance, vs.
     *   `(1 - relevanceWeight)` going to business signals) when none was saved in Zed yet.
     * - **0.75, not a neutral 0.5** — text relevance is deliberately favored as the primary signal, with
     *   business signals refining/tiebreaking rather than competing as an equal partner. Two reasons:
     *   (1) this package's own predecessor formula, `(1 + sqrt(_score)) * (business + baseline)`, was
     *   MULTIPLICATIVE — for this catalog's typical scores (roughly 4-20, see
     *   {@see getDefaultRelevanceSaturationPoint()}), that unbounded `1 + sqrt(_score)` term swung
     *   roughly 2x-5x across weak-to-strong text matches, structurally dominant over the bounded business
     *   term it multiplied. A flat 0.5/0.5 additive split on the current (correctly bounded) formula is
     *   NOT an equivalent balance — it under-weights text relevance relative to what this package
     *   previously did in practice; (2) this matches established field guidance (e.g. Turnbull &
     *   Berryman, "Relevant Search"): text relevance should stay the primary ranking signal, with
     *   business/popularity signals used to refine and tiebreak rather than override it — an equal-weight
     *   additive blend risks letting a popular-but-off-target result outrank an exact/obviously-right
     *   match, a common and easily user-visible search-relevance failure mode.
     * - This is still a starting point, not a measured optimum — this package doesn't decide what this
     *   value should be for a given shop, only how the blend is computed once it's set. An `nDCG`-style
     *   evaluation against real rated queries, run by separate tooling on top of this one, is one
     *   principled way to validate or refine it against a real catalog's own traffic, once enough ratings
     *   exist.
     *
     * @api
     */
    public function getDefaultRelevanceWeight(): float
    {
        return 0.75;
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
     */
    public function getDefaultRelevanceSaturationPoint(): float
    {
        return 12.0;
    }

    /**
     * Specification:
     * - Default blend weight (alpha) combining a query's per-term IDF values into one raw specificity
     *   value, when none was saved in Zed yet: `alpha * max(idf) + (1 - alpha) * harmonicMean(idf)`.
     * - **0.7, favoring `max`** — a query with one genuinely rare term (e.g. a SKU trailing a common
     *   word) should still read as specific even though its OTHER terms are common; weighting `max` more
     *   heavily than the harmonic mean achieves that, while the harmonic mean's own 0.3 share still lets
     *   an all-common-words query (no rare term at all) read as unspecific. Only meaningful once
     *   specificity-aware relevance weighting is enabled at the code level.
     *
     * @api
     */
    public function getDefaultSpecificityBlendWeight(): float
    {
        return 0.7;
    }

    /**
     * Specification:
     * - Default raw specificity value at which normalized specificity reaches 0.5, when none was saved in
     *   Zed yet. An unmeasured placeholder to tune from via Calibration — this package doesn't know a
     *   shop's own idf range in advance, since it depends entirely on catalog size and vocabulary (see
     *   {@see getDefaultRelevanceSaturationPoint()} for the same role on the text-relevance side).
     *
     * @api
     */
    public function getDefaultSpecificitySaturationPoint(): float
    {
        return 3.0;
    }

    /**
     * Specification:
     * - Default exponent reshaping how sharply the specificity-derived shift ramps up, when none was
     *   saved in Zed yet. `1.0` applies the shift linearly with no reshaping.
     *
     * @api
     */
    public function getDefaultSpecificityWeightExponent(): float
    {
        return 1.0;
    }

    /**
     * Specification:
     * - Default maximum amount the specificity-derived value may shift `relevanceWeight` away from its
     *   configured baseline, in either direction, when none was saved in Zed yet.
     * - **0.25, sized to match the 0.75 default baseline** (see {@see getDefaultRelevanceWeight()}):
     *   `shiftMagnitude = 1 - relevanceWeight`. With a baseline above 0.5, the shift has less headroom
     *   upward (toward 1.0) than downward (toward 0.0) before clamping; sizing the magnitude to exactly
     *   the tighter (upward) side means a maximally specific query (normalized specificity 1.0) reaches
     *   precisely `1.0` — pure text relevance — with no clamped/wasted resolution, while a maximally
     *   unspecific query (normalized specificity 0.0) floors at exactly `0.75 - 0.25 = 0.5`: the OLD
     *   global default, never lower. If the baseline default changes, re-derive this as
     *   `1 - relevanceWeight` again rather than leaving it fixed at 0.25.
     *
     * @api
     */
    public function getDefaultSpecificityWeightShiftMagnitude(): float
    {
        return 0.25;
    }

    /**
     * Specification:
     * - Number of product abstract publish events triggered per bulk when re-publishing scored products.
     *
     * @api
     *
     * @return positive-int
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
     */
    public function getRandomMetricName(): string
    {
        return 'random';
    }
}
