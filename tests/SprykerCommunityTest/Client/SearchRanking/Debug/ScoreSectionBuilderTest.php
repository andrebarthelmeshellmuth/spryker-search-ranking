<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Debug;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;
use SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Debug
 * @group ScoreSectionBuilderTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRanking\SearchRankingClientTester $tester
 */
class ScoreSectionBuilderTest extends Unit
{
    public function testBuildsOneLinePerMetricPlusTotal(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5, 'pdp_impressions' => 0.3])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, [
            'top_seller' => 0.5099,
            'pdp_impressions' => 0.2033,
        ], null);

        // Assert
        $this->assertSame('Business signals', $section['title']);
        $this->assertCount(2, $section['lines']);

        $this->assertSame('top_seller', $section['lines'][0]['label']);
        $this->assertSame('0.510 × 0.500', $section['lines'][0]['calculation']);
        $this->assertEqualsWithDelta(0.25495, $section['lines'][0]['value'], 1.0E-9);

        // The total sums the weighted metric contributions only — relevanceWeight/relevanceSaturationPoint
        // aren't business signals, they only ever appear as literal numbers inside the blend formula.
        $this->assertSame('Business signal total', $section['summaryLabel']);
        $this->assertEqualsWithDelta(0.25495 + 0.06099, $section['summaryValue'], 1.0E-9);

        $this->assertArrayNotHasKey('formulaCalculation', $section);
        // Only buildSpecificitySection()'s section carries this flag — the search-debug overlay uses it
        // to single out the "Specificity weighting" section for its own dedicated render position.
        $this->assertArrayNotHasKey('isSpecificitySection', $section);
    }

    /**
     * A product without a signal for some metric contributes 0 for it — the line still shows up, so the
     * displayed lines always account for the full total.
     */
    public function testShowsZeroContributionForAMissingDocumentScore(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, [], null);

        // Assert
        $this->assertSame('0.000 × 0.500', $section['lines'][0]['calculation']);
        $this->assertSame(0.0, $section['lines'][0]['value']);
        $this->assertSame(0.0, $section['summaryValue']);
    }

    public function testAddsTheCombinationFormulaWhenTheQueryScoreIsKnown(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, ['top_seller' => 0.5], 6.9244);

        // Assert: normalizedRelevance = queryScore / (queryScore + relevanceSaturationPoint)
        //   = 6.9244 / (6.9244 + 12.0) = 0.365864...
        $this->assertSame('Saturation point (k)', $section['relevanceSaturationPointLabel']);
        $this->assertSame(12.0, $section['relevanceSaturationPointValue']);
        $this->assertSame('Text Signal total', $section['normalizedRelevanceLabel']);
        $this->assertEqualsWithDelta(0.365898, $section['normalizedRelevanceValue'], 1.0E-6);
        $this->assertSame('Relevance weight (α)', $section['relevanceWeightLabel']);
        $this->assertSame(0.6, $section['relevanceWeightValue']);

        // Plugs in the already-shown normalizedRelevance value directly (rounded to SCORE_DECIMAL_PLACES,
        // 3) and spells out "(1 - relevanceWeight)" literally instead of pre-subtracting it into a single
        // number. Stops at the calculation, no result — the overlay's "Final score" line shows that same
        // number, so repeating it here would be redundant.
        $this->assertSame('0.600 × 0.366 + (1 - 0.600) × 0.250', $section['formulaCalculation']);
    }

    /**
     * A negative queryScore should never reach this class in practice (Elasticsearch `_score` is
     * non-negative), but the `$queryScore >= 0` guard exists specifically to keep it from ever plugging a
     * negative number into `queryScore / (queryScore + relevanceSaturationPoint)` — assert that guard
     * actually suppresses the combination formula rather than just trusting it silently does.
     */
    public function testOmitsTheCombinationFormulaWhenTheQueryScoreIsNegative(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, ['top_seller' => 0.5], -1.0);

        // Assert: the per-metric breakdown and total are still built, only the relevance/formula fields
        // (which would need a valid, non-negative queryScore) are left out.
        $this->assertSame('0.500 × 0.500', $section['lines'][0]['calculation']);
        $this->assertArrayNotHasKey('relevanceSaturationPointLabel', $section);
        $this->assertArrayNotHasKey('normalizedRelevanceValue', $section);
        $this->assertArrayNotHasKey('formulaCalculation', $section);
    }

    /**
     * Regression test: confirmed live against this shop's own DE/en_US ranking configuration, whose
     * metric weights were all 0 — FunctionScoreBuilder's OWN identical "$weight === 0.0 → skip" guard
     * (mirrored here as $hasActiveWeight) means it never wraps the query in that case, so the real final
     * `_score` is untouched plain text relevance. Before this fix, `formulaCalculation` was still added
     * whenever queryScore was merely known/non-negative — printing a formula whose OWN arithmetic
     * (relevanceWeight × normalizedRelevance + (1 - relevanceWeight) × 0.000) does NOT equal the real
     * final score sitting right below it in the overlay, silently contradicting the one number the
     * formula exists to make reproducible-by-eye. Every other field (the business-signal lines/total,
     * saturation point, normalized text relevance, relevance weight) is still a true statement about the
     * configured values and the query's own real text score either way, so only `formulaCalculation`
     * itself must disappear — not the whole section.
     */
    public function testOmitsOnlyTheCombinationFormulaWhenNoMetricHasAnActiveWeight(): void
    {
        // Arrange — two metrics configured (so $lines isn't empty, same as this shop's real config), both
        // weighted at 0.
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.0, 'pdp_impressions' => 0.0])
            ->setRelevanceWeight(0.744)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, [
            'top_seller' => 0.51,
            'pdp_impressions' => 0.20,
        ], 17.29);

        // Assert — everything else about the query's own real text relevance still shows...
        $this->assertCount(2, $section['lines']);
        $this->assertSame(0.0, $section['summaryValue']);
        $this->assertSame('Saturation point (k)', $section['relevanceSaturationPointLabel']);
        $this->assertSame('Text Signal total', $section['normalizedRelevanceLabel']);
        $this->assertEqualsWithDelta(17.29 / (17.29 + 12.0), $section['normalizedRelevanceValue'], 1.0E-9);
        $this->assertSame('Relevance weight (α)', $section['relevanceWeightLabel']);
        $this->assertSame(0.744, $section['relevanceWeightValue']);

        // ... except the formula, which would misrepresent how the real final score was produced.
        $this->assertArrayNotHasKey('formulaCalculation', $section);
    }

    /**
     * "random" is a noise-comparison metric, not a real business signal — kept last in the display order
     * regardless of where it was configured, so the metrics that actually explain the ranking read first.
     */
    public function testMovesTheRandomMetricToTheEndRegardlessOfConfiguredOrder(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['random' => 0.2, 'top_seller' => 0.5, 'pdp_impressions' => 0.3])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, [
            'random' => 0.75,
            'top_seller' => 0.51,
            'pdp_impressions' => 0.20,
        ], null);

        // Assert
        $this->assertSame(['top_seller', 'pdp_impressions', 'random'], array_column($section['lines'], 'label'));
    }

    public function testReturnsNullWhenNoMetricWeightsAreConfigured(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights([])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, ['top_seller' => 0.5], 1.0);

        // Assert
        $this->assertNull($section);
    }

    /**
     * A metric whose name fails FunctionScoreBuilder::METRIC_NAME_PATTERN (reachable via CSV data import,
     * which -- unlike the Zed form -- doesn't validate the name) is invisible to the real function_score
     * script: FunctionScoreBuilder skips it entirely. This overlay must skip it too, rather than showing
     * a nonzero contribution for a metric that never actually influenced the real score.
     */
    public function testExcludesAMetricWhoseNameFailsTheLiveScriptsNamingPattern(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5, 'Invalid-Name' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, [
            'top_seller' => 0.5,
            'Invalid-Name' => 0.9,
        ], null);

        // Assert
        $this->assertCount(1, $section['lines']);
        $this->assertSame('top_seller', $section['lines'][0]['label']);
        $this->assertEqualsWithDelta(0.25, $section['summaryValue'], 1.0E-9);
    }

    /**
     * If EVERY configured metric fails the naming pattern, FunctionScoreBuilder::build() returns null and
     * the real query is never blended at all -- the raw ES _score stands unchanged. This overlay must
     * return null too (no section, no fabricated "here's how the score was computed" formula), the same
     * way it already does for an empty metric-weights map.
     */
    public function testReturnsNullWhenEveryConfiguredMetricFailsTheLiveScriptsNamingPattern(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['Invalid-Name' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, ['Invalid-Name' => 0.9], 1.0);

        // Assert
        $this->assertNull($section);
    }

    /**
     * The configured relevanceWeight (0.6) must NOT leak into the formula/relevanceWeightValue once
     * specificity weighting actually adjusted it for this query (to 0.9 here) — otherwise the printed
     * formula would silently disagree with the real final score specificity weighting produced.
     */
    public function testUsesTheSpecificityAdjustedRelevanceWeightInTheFormulaWhenGiven(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        $specificityWeightingResult = $this->createSpecificityWeightingResult(0.6, 0.9, 0.1, 0.3, 8);

        // Act
        $section = (new ScoreSectionBuilder())->build(
            $configurationTransfer,
            ['top_seller' => 0.5],
            6.9244,
            $specificityWeightingResult,
        );

        // Assert: normalizedRelevance is unaffected by specificity (only relevanceWeight is)
        $this->assertSame(0.9, $section['relevanceWeightValue']);
        $this->assertSame('0.900 × 0.366 + (1 - 0.900) × 0.250', $section['formulaCalculation']);
    }

    public function testBuildsASpecificitySectionWithOneLinePerDiagnostic(): void
    {
        // Arrange -- shiftMagnitude=0.5, exponent=2.0 chosen so the resulting calculation string is
        // arithmetically real (0.5 × (0.6)^2.0 = 0.18), not just plausible-looking, and so a
        // non-default exponent (the whole reason it's shown at all -- see calculateShift()'s own
        // docblock) actually appears in the fixture. rawSpecificity=6.0/saturationPoint=3.0/
        // curveExponent=2.0 chosen the same way: 6.0^2 / (6.0^2 + 3.0^2) = 36/45 = 0.8, the exact
        // normalizedSpecificity given below -- so the new "Normalized specificity" calculation string is
        // real arithmetic too, not just plausible-looking.
        $specificityWeightingResult = $this->createSpecificityWeightingResult(0.75, 0.93, 0.8, 0.18, 10, 2.0, 0.5, 6.0, 3.0, 2.0);

        // Act
        $section = (new ScoreSectionBuilder())->buildSpecificitySection($specificityWeightingResult);

        // Assert
        $this->assertSame('Specificity weighting', $section['title']);
        // The search-debug overlay reads this flag to render this section in its own dedicated spot
        // (above the relevance-weight line) instead of the default top-of-page position.
        $this->assertTrue($section['isSpecificitySection']);
        // No "Query term count" line (purely informational, dropped as unhelpful) and no trailing
        // "Effective relevance weight (α)" line (the exact same number as build()'s own
        // RELEVANCE_WEIGHT_LABEL line, which already renders right below this collapsible section,
        // outside the fold -- see buildSpecificitySection()'s own closing comment).
        $this->assertCount(7, $section['lines']);

        $this->assertSame('Configured relevance weight (α₀)', $section['lines'][0]['label']);
        $this->assertSame(0.75, $section['lines'][0]['value']);

        $this->assertSame('Saturation point (k)', $section['lines'][1]['label']);
        $this->assertSame(3.0, $section['lines'][1]['value']);

        $this->assertSame('Raw specificity', $section['lines'][2]['label']);
        $this->assertSame(6.0, $section['lines'][2]['value']);

        $this->assertSame('Normalized specificity', $section['lines'][3]['label']);
        // Mirrors QuerySpecificityCalculator::normalize()'s own general (exponent-shaped) formula, always
        // shown with the `^` notation even at exponent 1.0 -- same convention the shift line's own
        // exponent already follows below.
        $this->assertSame('6.000^2.000 / (6.000^2.000 + 3.000^2.000)', $section['lines'][3]['calculation']);
        $this->assertSame(0.8, $section['lines'][3]['value']);
        // `stacked` opts this line into the label-above-value layout (search-debug-score-line--stacked)
        // instead of the default side-by-side one -- the full expression above is too long for the
        // default layout without squeezing the label to near-zero width (confirmed live).
        $this->assertTrue($section['lines'][3]['stacked']);

        // The bridge between "Normalized specificity" and "Shift applied to α" -- without this line, a
        // reader has no way to see how 0.8 becomes 0.18 three lines below.
        $this->assertSame('Signed deviation', $section['lines'][4]['label']);
        // Plugs in the already-shown normalized specificity value (0.800) directly.
        $this->assertSame('2 × 0.800 - 1', $section['lines'][4]['calculation']);
        $this->assertEqualsWithDelta(0.6, $section['lines'][4]['value'], 1.0E-9);
        // Same squeeze/overflow reason as the normalized-specificity line above -- confirmed live even
        // THIS line's own short label ellipsis-truncates under the default layout.
        $this->assertTrue($section['lines'][4]['stacked']);

        // Gives the shift line's calculation string its own labeled source for the "0.500" it plugs in,
        // rather than that number appearing unexplained inside the formula.
        $this->assertSame('Shift magnitude', $section['lines'][5]['label']);
        $this->assertSame(0.5, $section['lines'][5]['value']);

        $this->assertSame('Shift applied to α', $section['lines'][6]['label']);
        // Plugs in the shift magnitude (0.500), the deviation's sign as its own explicit factor (1, since
        // 0.600 is positive here), and the deviation's MAGNITUDE raised to the exponent -- mirroring
        // calculateShift()'s real sign-preserving computation exactly (see the surrounding code's own
        // comment for why folding the signed value straight into the power would be wrong).
        $this->assertSame('0.500 × 1 × 0.600^2.000', $section['lines'][6]['calculation']);
        $this->assertSame(0.18, $section['lines'][6]['value']);
    }

    /**
     * Regression test: a query LESS specific than typical (normalizedSpecificity below the neutral 0.5
     * point, the common case for a generic query) produces a NEGATIVE signed deviation. With a non-1.0
     * exponent, literally evaluating "(signedDeviation)^exponent" would give the WRONG sign for an even
     * exponent -- calculateShift() itself avoids this by applying the exponent to the deviation's
     * MAGNITUDE only and reattaching the sign afterward (see that method's own docblock), and this
     * section's displayed calculation string must do the exact same thing, not just look plausible.
     */
    public function testShiftCalculationPreservesTheSignForANegativeDeviationWithANonDefaultExponent(): void
    {
        // Arrange -- normalizedSpecificity=0.3 -> signedDeviation = 2 × 0.3 - 1 = -0.4 (negative).
        // exponent=2.0, shiftMagnitude=0.5 -> real shift = 0.5 × sign(-0.4) × |-0.4|^2.0 = 0.5 × -1 × 0.16 = -0.08.
        $specificityWeightingResult = $this->createSpecificityWeightingResult(0.75, 0.67, 0.3, -0.08, 10, 2.0, 0.5, 1.0, 1.0, 1.0);

        // Act
        $section = (new ScoreSectionBuilder())->buildSpecificitySection($specificityWeightingResult);

        // Assert
        $this->assertEqualsWithDelta(-0.4, $section['lines'][4]['value'], 1.0E-9);
        // The sign (-1) is its own explicit factor, and the exponent applies only to the magnitude
        // (0.400) -- literally evaluating this string gives 0.500 × -1 × 0.160 = -0.08, matching `value`
        // exactly. The old "(signedDeviation)^exponent" form would instead have literally evaluated to
        // 0.500 × (-0.400)^2.000 = +0.08 -- the wrong sign.
        $this->assertSame('0.500 × -1 × 0.400^2.000', $section['lines'][6]['calculation']);
        $this->assertSame(-0.08, $section['lines'][6]['value']);
    }

    /**
     * Same field order/defaults SpecificityWeightCalculator::calculateWeightingResult() itself sets via
     * fluent setters — kept as its own helper purely so every test above can build a fixture with a plain
     * positional argument list, same shape the old hand-written value object's constructor offered.
     *
     * @param float $configuredRelevanceWeight
     * @param float $relevanceWeight
     * @param float $normalizedSpecificity
     * @param float $shift
     * @param int $queryTermCount
     * @param float $specificityWeightExponent
     * @param float $specificityWeightShiftMagnitude
     * @param float $rawSpecificity
     * @param float $specificitySaturationPoint
     * @param float $specificityCurveExponent
     */
    protected function createSpecificityWeightingResult(
        float $configuredRelevanceWeight,
        float $relevanceWeight,
        float $normalizedSpecificity,
        float $shift,
        int $queryTermCount,
        float $specificityWeightExponent = 1.0,
        float $specificityWeightShiftMagnitude = 0.25,
        float $rawSpecificity = 0.0,
        float $specificitySaturationPoint = 0.0,
        float $specificityCurveExponent = 1.0,
    ): SearchRankingSpecificityWeightingResultTransfer {
        return (new SearchRankingSpecificityWeightingResultTransfer())
            ->setConfiguredRelevanceWeight($configuredRelevanceWeight)
            ->setRelevanceWeight($relevanceWeight)
            ->setNormalizedSpecificity($normalizedSpecificity)
            ->setShift($shift)
            ->setQueryTermCount($queryTermCount)
            ->setSpecificityWeightExponent($specificityWeightExponent)
            ->setSpecificityWeightShiftMagnitude($specificityWeightShiftMagnitude)
            ->setRawSpecificity($rawSpecificity)
            ->setSpecificitySaturationPoint($specificitySaturationPoint)
            ->setSpecificityCurveExponent($specificityCurveExponent);
    }
}
