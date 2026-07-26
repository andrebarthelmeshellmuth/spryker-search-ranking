<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

interface ShannonEntropyCalculatorInterface
{
    /**
     * Specification:
     * - Normalized Shannon entropy of a set of non-negative scores, in `[0;1]`.
     * - Treats the scores as a "relevance mass" distribution (plain ratio to their sum, NOT softmax —
     *   softmax would artificially sharpen the distribution regardless of the actual score gaps).
     * - `0.0` when there is one dominant score (a clear single leader — text relevance already
     *   discriminates well); `1.0` when all scores are equal (text relevance can't tell candidates
     *   apart at all).
     * - Fewer than 2 scores, or scores summing to `0.0`, both return `0.0` — there is no distribution to
     *   measure.
     *
     * @api
     *
     * @param array<float> $scores
     *
     * @return float
     */
    public function calculateNormalizedEntropy(array $scores): float;
}
