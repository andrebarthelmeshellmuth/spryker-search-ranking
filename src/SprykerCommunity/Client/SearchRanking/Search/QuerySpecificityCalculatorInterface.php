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
     * - Maps the unbounded `$rawSpecificity` into `[0;1[` via the Hill-equation generalization of the
     *   saturating shape used elsewhere in this package: `raw^p / (raw^p + k^p)`. `$curveExponent` (`p`)
     *   defaults to `1.0`, reproducing the original plain `raw / (raw + k)` shape exactly (byte-for-byte —
     *   the `p=1.0` case takes a dedicated fast path that never evaluates `**` at all).
     * - `$saturationPoint` (`k`) is the raw specificity at which the normalized result reaches exactly
     *   `0.5` — calibrated per shop the same way `relevanceSaturationPoint` is (see Calibration), since
     *   what counts as a "typically specific" query depends entirely on the shop's own catalog and query
     *   patterns. This stays true for ANY `$curveExponent`: `raw = k` always maps to exactly `0.5`,
     *   regardless of `p` (`k^p / (k^p + k^p) = 0.5` for any `p`), so raising `p` never moves the pivot
     *   `k` itself means — it only sharpens the transition around it, pushing both tails further toward
     *   their `0`/`1` bounds.
     *
     * @api
     *
     * @param float $rawSpecificity
     * @param float $saturationPoint
     * @param float $curveExponent
     */
    public function normalize(float $rawSpecificity, float $saturationPoint, float $curveExponent = 1.0): float;
}
