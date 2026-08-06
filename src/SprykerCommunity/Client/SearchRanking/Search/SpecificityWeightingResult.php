<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

/**
 * Immutable snapshot of one {@see \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculatorInterface}
 * outcome — carries both the `relevanceWeight` that was actually applied to the real query AND the
 * diagnostics behind it (query term count, normalized specificity, shift), so a consumer like the
 * search-debug overlay can show the SAME numbers that produced the real score, not just the end result.
 *
 * A failed probe, or a query with no terms carrying any real corpus evidence, still produces one of
 * these: `relevanceWeight` equals `configuredRelevanceWeight` unchanged, and `normalizedSpecificity`/
 * `shift` are `0.0` with `queryTermCount` `0` — there was simply nothing to measure, not an error.
 */
class SpecificityWeightingResult
{
    /**
     * @param float $configuredRelevanceWeight
     * @param float $relevanceWeight
     * @param float $normalizedSpecificity
     * @param float $shift
     * @param int $queryTermCount
     * @param float $specificityWeightExponent The exponent `shift` was actually shaped with — carried
     *   here (not just implied by `$shift`'s own value) so a consumer like the search-debug overlay can
     *   show the calculation that produced `$shift`, not just the result. Defaults to `1.0`, the
     *   exponent's own identity value, matching every other "no shift happened" fallback here.
     * @param float $specificityWeightShiftMagnitude The `shiftMagnitude` `shift` was actually scaled by —
     *   same reasoning as `$specificityWeightExponent`. Defaults to
     *   {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getDefaultSpecificityWeightShiftMagnitude()}'s
     *   own default value.
     */
    public function __construct(
        protected float $configuredRelevanceWeight,
        protected float $relevanceWeight,
        protected float $normalizedSpecificity,
        protected float $shift,
        protected int $queryTermCount,
        protected float $specificityWeightExponent = 1.0,
        protected float $specificityWeightShiftMagnitude = 0.25,
    ) {
    }

    /**
     * The statically configured `relevanceWeight`, before any specificity-derived shift.
     */
    public function getConfiguredRelevanceWeight(): float
    {
        return $this->configuredRelevanceWeight;
    }

    /**
     * The `relevanceWeight` actually used to score the query this result belongs to.
     */
    public function getRelevanceWeight(): float
    {
        return $this->relevanceWeight;
    }

    public function getNormalizedSpecificity(): float
    {
        return $this->normalizedSpecificity;
    }

    public function getShift(): float
    {
        return $this->shift;
    }

    /**
     * How many of the query's terms carried real corpus evidence (a nonzero `doc_freq` for at least one
     * probed field) and therefore contributed to the specificity calculation (`0` when none did, or the
     * probe returned no hits or failed).
     */
    public function getQueryTermCount(): int
    {
        return $this->queryTermCount;
    }

    public function getSpecificityWeightExponent(): float
    {
        return $this->specificityWeightExponent;
    }

    public function getSpecificityWeightShiftMagnitude(): float
    {
        return $this->specificityWeightShiftMagnitude;
    }
}
