<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Debug;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;

/**
 * Builds the business-signal breakdown section for the search-debug SRP overlay: one line per
 * weighted metric (normalized signal × weight = contribution), their sum, and — when the wrapped
 * query's own relevance score is known — the saturation-point/normalization/weight values plus the
 * full blend formula, so the final `_score` is reproducible by eye. This section's OWN array only ever
 * holds these values as data (`relevanceSaturationPointLabel`/`Value`, `normalizedRelevanceLabel`/
 * `Value`, `relevanceWeightLabel`/`Value`, `formulaCalculation`) — the search-debug overlay template
 * decides where each one actually renders, and deliberately does NOT print them as one contiguous
 * block: the saturation point sits grouped with the raw text-match score it normalizes, the normalized
 * result gets its own standalone line right after, and the relevance weight sits directly next to the
 * closing formula, with search-ranking's own "Specificity weighting" section (see
 * {@see buildSpecificitySection()}) placed between the two:
 *
 *   top_seller: 0.51 × 0.50 = 0.26
 *   pdp_impressions: 0.20 × 0.30 = 0.06
 *   Business signal total: 0.32
 *   ...
 *   Saturation point (k): 12.00 <- grouped with the raw text-match score, under "Text signals"
 *   Text Signal total: 0.37 <- its own standalone line, right after
 *   ...
 *   Specificity weighting: ... <- search-ranking's other section, when it ran for this query
 *   Relevance weight (α): 0.50
 *   0.50 × 0.37 + (1 - 0.50) × 0.32 =
 *
 * The formula deliberately stops at "=" without repeating the result — the search-debug overlay
 * already shows that same number right below, as the final score. Spelling it out twice would just be
 * redundant. It plugs in the ALREADY-shown "Text Signal total" value directly rather than repeating the
 * `queryScore / (queryScore + relevanceSaturationPoint)` sub-expression inline — that expression has
 * its own line elsewhere in the overlay for exactly this reason. The `(1 - relevanceWeight)` half is
 * spelled out literally (not pre-subtracted into a single number) so it stays visibly tied to the
 * `relevanceWeight` value shown just above the formula, rather than reading as some other, unexplained
 * constant.
 *
 * `relevanceWeight` and `relevanceSaturationPoint` (see {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder}
 * for the full rationale) get their own labeled lines (using the same α/k shorthand the README's
 * formula uses) rather than only ever appearing as literal numbers plugged into the formula — unlike a
 * metric's own weight, which stays inline in "signal × weight = contribution" only.
 *
 * Every number here is rounded to {@see SearchDebugConfig::SCORE_DECIMAL_PLACES} — the SAME constant
 * the search-debug overlay itself uses for every other number it shows (`_score`, matched-token
 * weights, other contributions), so the whole overlay reads at one consistent precision. This class
 * only exists to feed that overlay (registered via the optional `ProductDebugDataExpanderPluginInterface`
 * integration — see spryker-community/search-debug), so depending on its Shared config here is an
 * intentional, feature-level coupling, not an accident.
 */
class ScoreSectionBuilder implements ScoreSectionBuilderInterface
{
    /**
     * Mirrors {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder::METRIC_NAME_PATTERN}
     * exactly — duplicated, not imported, to keep this Debug-layer class from reaching into the
     * Query-layer class for a single regex literal (same layering rationale as the DataImport writer's
     * own duplicate of this pattern). A metric whose name fails this check is invisible to the real
     * `function_score` script (FunctionScoreBuilder skips it entirely), so this overlay must skip it too
     * -- otherwise a data-import-bypassed metric (validated by the Zed form, not by import) shows a
     * nonzero contribution and a "this is how the real score was computed" formula here that never
     * actually ran against the live query.
     *
     * @var string
     */
    protected const METRIC_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * "random" is a deliberately non-business-driven metric (a noise baseline for comparison, see
     * fixtures) — kept last in the display order so the metrics that actually explain *why* a product
     * ranked where it did read first, with the noise metric trailing rather than interleaved among them.
     *
     * @var string
     */
    protected const METRIC_NAME_RANDOM = 'random';

    /**
     * @var string
     */
    protected const SECTION_TITLE = 'Business signals';

    /**
     * @var string
     */
    protected const SUMMARY_LABEL = 'Business signal total';

    /**
     * @var string
     */
    protected const RELEVANCE_SATURATION_POINT_LABEL = 'Saturation point (k)';

    /**
     * Renders as its own standalone line, not nested under "Saturation point (k)" — see this class's own
     * docblock for where each field in this section actually renders.
     *
     * @var string
     */
    protected const NORMALIZED_RELEVANCE_LABEL = 'Text Signal total';

    /**
     * @var string
     */
    protected const RELEVANCE_WEIGHT_LABEL = 'Relevance weight (α)';

    /**
     * @var string
     */
    protected const SPECIFICITY_SECTION_TITLE = 'Specificity weighting';

    /**
     * @var string
     */
    protected const SPECIFICITY_CONFIGURED_WEIGHT_LABEL = 'Configured relevance weight (α₀)';

    /**
     * Same short "(k)" shorthand {@see RELEVANCE_SATURATION_POINT_LABEL} already uses for the OTHER
     * (text-relevance) saturation point — this is specificity's own, a different configured value,
     * unambiguous since the two never render outside their own sections.
     *
     * @var string
     */
    protected const SPECIFICITY_SATURATION_POINT_LABEL = 'Saturation point (k)';

    /**
     * The pre-normalization blended-IDF value {@see SPECIFICITY_NORMALIZED_LABEL}'s own `calculation`
     * string below plugs in as its numerator — given its own labeled line first, same convention
     * `SPECIFICITY_SHIFT_MAGNITUDE_LABEL` already follows for the shift formula's own inputs.
     *
     * @var string
     */
    protected const SPECIFICITY_RAW_LABEL = 'Raw specificity';

    /**
     * @var string
     */
    protected const SPECIFICITY_NORMALIZED_LABEL = 'Normalized specificity';

    /**
     * The centering step between "Normalized specificity" and "Shift applied to α": maps the `[0;1[`
     * normalized specificity onto a signed `[-1;1]` deviation from the neutral point (0.5 → 0), which is
     * what {@see \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator::calculateShift()}
     * actually shapes/scales into the shift — without this line, a reader has no way to see how e.g.
     * `0.463` becomes `-0.018` two lines below. Deliberately doesn't spell out "(2 × specificity − 1)" in
     * the LABEL itself — the overlay's `.search-debug-score-line__label` is a fixed-width, single-line,
     * ellipsis-truncated span (see search-debug's own SCSS), and that formula pushed this label well past
     * every sibling label's length, truncating mid-word. Shown instead as this line's own `calculation`
     * string in `buildSpecificitySection()`, where there's room for it.
     *
     * @var string
     */
    protected const SPECIFICITY_SIGNED_DEVIATION_LABEL = 'Signed deviation';

    /**
     * Gives `shiftMagnitude` its own labeled line, directly above the "Shift applied to α" line whose
     * `calculation` string uses it as a bare number — same reasoning `build()`'s own relevanceWeight/
     * relevanceSaturationPoint lines already follow (see this class's own top docblock): a constant that
     * feeds a formula gets shown labeled once, immediately before the formula that consumes it, rather
     * than only ever appearing unexplained inside that formula.
     *
     * @var string
     */
    protected const SPECIFICITY_SHIFT_MAGNITUDE_LABEL = 'Shift magnitude';

    /**
     * @var string
     */
    protected const SPECIFICITY_SHIFT_LABEL = 'Shift applied to α';

    /**
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<string, float> $documentScores
     * @param float|null $queryScore
     * @param \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult|null $specificityWeightingResult
     *
     * @return array<string, mixed>|null
     */
    public function build(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        array $documentScores,
        ?float $queryScore,
        ?SpecificityWeightingResult $specificityWeightingResult = null,
    ): ?array {
        $lines = [];
        $signalTotal = 0.0;
        // Mirrors FunctionScoreBuilder::buildScript()'s OWN "$weight === 0.0 → skip" guard — duplicated
        // for the same layering reason METRIC_NAME_PATTERN already is (see this class's own docblock):
        // FunctionScoreBuilder wraps the query in a real function_score IF AND ONLY IF at least one
        // metric has a non-zero weight, so this flag reliably answers "did that wrapping actually happen
        // for this query" without this Debug-layer class needing to inspect the explain tree itself. It
        // gates ONLY {@see formulaCalculation} below — the one field that actively CLAIMS to reproduce
        // the real final `_score`; every other field here (business-signal lines/total, saturation point,
        // normalized relevance, relevance weight) stays a true statement about the CONFIGURED values and
        // the query's own real text-relevance score either way, function_score or not.
        $hasActiveWeight = false;
        $decimalFormat = '%.' . SearchDebugConfig::SCORE_DECIMAL_PLACES . 'f';

        foreach ($configurationTransfer->getMetricWeights() as $metricName => $weight) {
            if (!is_string($metricName) || !preg_match(static::METRIC_NAME_PATTERN, $metricName)) {
                continue;
            }

            $weight = (float)$weight;

            if ($weight !== 0.0) {
                $hasActiveWeight = true;
            }

            $signal = (float)($documentScores[$metricName] ?? 0.0);
            $contribution = $signal * $weight;
            $signalTotal += $contribution;

            $lines[] = [
                'label' => $metricName,
                'calculation' => sprintf($decimalFormat . ' × ' . $decimalFormat, $signal, $weight),
                'value' => $contribution,
            ];
        }

        if ($lines === []) {
            return null;
        }

        $lines = $this->withRandomMetricLast($lines);

        $section = [
            'title' => static::SECTION_TITLE,
            'lines' => $lines,
            'summaryLabel' => static::SUMMARY_LABEL,
            'summaryValue' => $signalTotal,
        ];

        if ($queryScore !== null && $queryScore >= 0) {
            // Specificity weighting, when it ran for this query, replaced the configured relevanceWeight
            // with a per-query one BEFORE the real function_score was built — read from there instead of
            // $configurationTransfer's static value, so this line (and the formula below) stay
            // reproducible-by-eye against the real final score. See SpecificityWeightingResult's docblock.
            $relevanceWeight = $specificityWeightingResult !== null
                ? $specificityWeightingResult->getRelevanceWeight()
                : (float)$configurationTransfer->getRelevanceWeight();
            $relevanceSaturationPoint = (float)$configurationTransfer->getRelevanceSaturationPoint();
            // $relevanceSaturationPoint is always > 0 (Zed form enforces GreaterThan(0)), so this never
            // divides by zero even when $queryScore is 0.
            $normalizedRelevance = $queryScore / ($queryScore + $relevanceSaturationPoint);

            $section['relevanceSaturationPointLabel'] = static::RELEVANCE_SATURATION_POINT_LABEL;
            $section['relevanceSaturationPointValue'] = $relevanceSaturationPoint;
            $section['normalizedRelevanceLabel'] = static::NORMALIZED_RELEVANCE_LABEL;
            $section['normalizedRelevanceValue'] = $normalizedRelevance;
            $section['relevanceWeightLabel'] = static::RELEVANCE_WEIGHT_LABEL;
            $section['relevanceWeightValue'] = $relevanceWeight;

            // Only shown when function_score actually ran (see $hasActiveWeight above): with every
            // metric weighted at 0, FunctionScoreBuilder never wraps the query at all, so the real final
            // `_score` is untouched plain text relevance — this formula's own arithmetic (relevanceWeight
            // × normalizedRelevance + ...) would then NOT equal the final score shown right below it,
            // silently contradicting the one number the overlay is supposed to make reproducible-by-eye.
            // Every other field above stays visible regardless — they're true configured/computed values
            // either way — only this line actively claims to explain how the final score was produced.
            if ($hasActiveWeight) {
                // Plugs in $normalizedRelevance directly (already shown on its own line above) instead of
                // repeating "queryScore / (queryScore + relevanceSaturationPoint)" inline, and spells out
                // "(1 - relevanceWeight)" literally rather than pre-subtracting it into a single number —
                // so the second term stays visibly tied to the relevanceWeight value shown just above.
                // Stops at "=" rather than also computing/showing the result — the search-debug overlay
                // already shows that same number right below, as the final score; repeating it here would
                // just be redundant.
                $section['formulaCalculation'] = sprintf(
                    $decimalFormat . ' × ' . $decimalFormat . ' + (1 - ' . $decimalFormat . ') × ' . $decimalFormat,
                    $relevanceWeight,
                    $normalizedRelevance,
                    $relevanceWeight,
                    $signalTotal,
                );
            }
        }

        return $section;
    }

    /**
     * {@inheritDoc}
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult $specificityWeightingResult
     *
     * @return array<string, mixed>
     */
    public function buildSpecificitySection(SpecificityWeightingResult $specificityWeightingResult): array
    {
        $decimalFormat = '%.' . SearchDebugConfig::SCORE_DECIMAL_PLACES . 'f';

        // Same centering `calculateShift()` itself does — see that method's own docblock. Computed here,
        // not carried on SpecificityWeightingResult, since it's trivially re-derivable from
        // normalizedSpecificity alone and doesn't need its own snapshot field.
        $signedDeviation = (2 * $specificityWeightingResult->getNormalizedSpecificity()) - 1;

        return [
            'title' => static::SPECIFICITY_SECTION_TITLE,
            // Tells the search-debug overlay template to render this section in its own dedicated spot
            // (directly above the relevance-weight line it explains the shift for) instead of the default
            // top-of-page position every other section (e.g. "Business signals") uses — see
            // ProductDebugDataExpanderPluginInterface's docblock for the full contract.
            'isSpecificitySection' => true,
            'lines' => [
                [
                    'label' => static::SPECIFICITY_CONFIGURED_WEIGHT_LABEL,
                    'value' => $specificityWeightingResult->getConfiguredRelevanceWeight(),
                ],
                [
                    'label' => static::SPECIFICITY_SATURATION_POINT_LABEL,
                    'value' => $specificityWeightingResult->getSpecificitySaturationPoint(),
                ],
                [
                    'label' => static::SPECIFICITY_RAW_LABEL,
                    'value' => $specificityWeightingResult->getRawSpecificity(),
                ],
                [
                    'label' => static::SPECIFICITY_NORMALIZED_LABEL,
                    // Mirrors QuerySpecificityCalculator::normalize()'s own general formula exactly —
                    // always shown with the `^` exponent notation, even at its default 1.0, same
                    // "never make a reader guess whether it was applied" convention the shift line below
                    // already follows for its own exponent. `stacked` (see the search-debug twig
                    // template's own comment) is load-bearing, not decorative: the full numerator +
                    // exponent + denominator + exponent expression is too long for the overlay's default
                    // side-by-side label/value layout — that layout squeezes the LABEL down to near-zero
                    // width instead of wrapping the value, and the popup then clips whatever no longer
                    // fits (confirmed live). Stacking lets the value wrap normally underneath its label.
                    'calculation' => sprintf(
                        $decimalFormat . '^' . $decimalFormat . ' / (' . $decimalFormat . '^' . $decimalFormat . ' + ' . $decimalFormat . '^' . $decimalFormat . ')',
                        $specificityWeightingResult->getRawSpecificity(),
                        $specificityWeightingResult->getSpecificityCurveExponent(),
                        $specificityWeightingResult->getRawSpecificity(),
                        $specificityWeightingResult->getSpecificityCurveExponent(),
                        $specificityWeightingResult->getSpecificitySaturationPoint(),
                        $specificityWeightingResult->getSpecificityCurveExponent(),
                    ),
                    'value' => $specificityWeightingResult->getNormalizedSpecificity(),
                    'stacked' => true,
                ],
                [
                    'label' => static::SPECIFICITY_SIGNED_DEVIATION_LABEL,
                    // Plugs in the ALREADY-shown normalized specificity value directly, same convention
                    // the shift line below follows for its own inputs — see this label's own docblock for
                    // why the formula isn't spelled out in the LABEL itself. `stacked` for the same
                    // squeeze/overflow reason the normalized-specificity line above uses it — confirmed
                    // live even THIS line's own short label ("Signed deviation") ellipsis-truncates under
                    // the default layout once the sibling value line grows past one line.
                    'calculation' => sprintf(
                        '2 × ' . $decimalFormat . ' - 1',
                        $specificityWeightingResult->getNormalizedSpecificity(),
                    ),
                    'stacked' => true,
                    'value' => $signedDeviation,
                ],
                [
                    'label' => static::SPECIFICITY_SHIFT_MAGNITUDE_LABEL,
                    'value' => $specificityWeightingResult->getSpecificityWeightShiftMagnitude(),
                ],
                [
                    'label' => static::SPECIFICITY_SHIFT_LABEL,
                    // Plugs in the two ALREADY-shown values (signed deviation, shift magnitude) directly
                    // rather than repeating "2 × specificity − 1" inline — same convention
                    // build()'s own formulaCalculation uses for normalizedRelevance (see this class's own
                    // top docblock). Exponent is shown only via the `^` notation, not its own line — it's
                    // reasonably self-labeling (unlike the bare shiftMagnitude number before it), and
                    // giving every one of 3 inputs its own line would make this section longer than the
                    // hover overlay comfortably fits. Always shown, even at its default 1.0, since it can
                    // genuinely differ per store/locale and a reader shouldn't have to guess whether it
                    // was applied.
                    'calculation' => sprintf(
                        $decimalFormat . ' × (' . $decimalFormat . ')^' . $decimalFormat,
                        $specificityWeightingResult->getSpecificityWeightShiftMagnitude(),
                        $signedDeviation,
                        $specificityWeightingResult->getSpecificityWeightExponent(),
                    ),
                    'value' => $specificityWeightingResult->getShift(),
                ],
                // Deliberately stops here, without its own "Effective relevance weight (α)" line — that
                // exact same number (getRelevanceWeight()) already renders right below this collapsible
                // section, OUTSIDE the fold, as build()'s own RELEVANCE_WEIGHT_LABEL line (see this
                // class's own top docblock for where it sits). Repeating it inside the fold too would be
                // the same value shown twice a few pixels apart.
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     *
     * @return array<int, array<string, mixed>>
     */
    protected function withRandomMetricLast(array $lines): array
    {
        $orderedLines = [];
        $randomLines = [];

        foreach ($lines as $line) {
            if ($line['label'] === static::METRIC_NAME_RANDOM) {
                $randomLines[] = $line;

                continue;
            }

            $orderedLines[] = $line;
        }

        return array_merge($orderedLines, $randomLines);
    }
}
