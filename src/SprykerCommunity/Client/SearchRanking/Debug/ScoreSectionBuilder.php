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
     * @var string
     */
    protected const SPECIFICITY_NORMALIZED_LABEL = 'Normalized specificity';

    /**
     * @var string
     */
    protected const SPECIFICITY_SHIFT_LABEL = 'Shift applied to α';

    /**
     * @var string
     */
    protected const SPECIFICITY_EFFECTIVE_WEIGHT_LABEL = 'Effective relevance weight (α)';

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
                    'label' => static::SPECIFICITY_NORMALIZED_LABEL,
                    'value' => $specificityWeightingResult->getNormalizedSpecificity(),
                ],
                [
                    'label' => static::SPECIFICITY_SHIFT_LABEL,
                    'value' => $specificityWeightingResult->getShift(),
                ],
                [
                    'label' => static::SPECIFICITY_EFFECTIVE_WEIGHT_LABEL,
                    'value' => $specificityWeightingResult->getRelevanceWeight(),
                ],
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
