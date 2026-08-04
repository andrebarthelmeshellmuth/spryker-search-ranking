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
     */
    public function __construct(
        protected float $configuredRelevanceWeight,
        protected float $relevanceWeight,
        protected float $normalizedSpecificity,
        protected float $shift,
        protected int $queryTermCount,
    ) {
    }

    /**
     * The statically configured `relevanceWeight`, before any specificity-derived shift.
     *
     * @return float
     */
    public function getConfiguredRelevanceWeight(): float
    {
        return $this->configuredRelevanceWeight;
    }

    /**
     * The `relevanceWeight` actually used to score the query this result belongs to.
     *
     * @return float
     */
    public function getRelevanceWeight(): float
    {
        return $this->relevanceWeight;
    }

    /**
     * @return float
     */
    public function getNormalizedSpecificity(): float
    {
        return $this->normalizedSpecificity;
    }

    /**
     * @return float
     */
    public function getShift(): float
    {
        return $this->shift;
    }

    /**
     * How many of the query's terms carried real corpus evidence (a nonzero `doc_freq` for at least one
     * probed field) and therefore contributed to the specificity calculation (`0` when none did, or the
     * probe returned no hits or failed).
     *
     * @return int
     */
    public function getQueryTermCount(): int
    {
        return $this->queryTermCount;
    }
}
