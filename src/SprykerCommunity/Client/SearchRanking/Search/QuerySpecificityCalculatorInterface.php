<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

interface QuerySpecificityCalculatorInterface
{
    /**
     * Specification:
     * - Blends a query's per-term IDF (`ln(N/df)`, higher = rarer = more specific) into one unbounded,
     *   non-negative "raw specificity" value: `blendWeight * max(idf) + (1 - blendWeight) * harmonicMean(idf)`.
     * - `max` alone rewards a single rare term even in an otherwise generic query (e.g. a SKU trailing a
     *   common word); `harmonicMean` alone punishes a query as soon as ANY term is common, even alongside
     *   a very rare one. Blending the two (default `blendWeight = 0.7`, favoring `max`) keeps a query with
     *   one genuinely rare term reading as specific, while still letting an all-common-words query (no
     *   rare term at all) read as unspecific.
     * - Zero terms returns `0.0` (nothing to measure). Exactly one term returns that term's own idf
     *   directly — `max` and `harmonicMean` are trivially identical for a single value, so `$blendWeight`
     *   has no effect in this case.
     * - Any idf value of `0.0` (a term present in literally every document) collapses the harmonic mean to
     *   `0.0` too — the correct mathematical limit, not a special case to guard against.
     *
     * @api
     *
     * @param array<string, float> $idfByTerm
     * @param float $blendWeight
     */
    public function calculateRawSpecificity(array $idfByTerm, float $blendWeight): float;

    /**
     * Specification:
     * - Maps the unbounded `$rawSpecificity` into `[0;1[` via the same saturating shape used elsewhere in
     *   this package for turning an unbounded raw value into a normalized one: `raw / (raw + k)`.
     * - `$saturationPoint` (`k`) is the raw specificity at which the normalized result reaches exactly
     *   `0.5` — calibrated per shop the same way `relevanceSaturationPoint` is (see Calibration), since
     *   what counts as a "typically specific" query depends entirely on the shop's own catalog and query
     *   patterns.
     *
     * @api
     *
     * @param float $rawSpecificity
     * @param float $saturationPoint
     */
    public function normalize(float $rawSpecificity, float $saturationPoint): float;
}
