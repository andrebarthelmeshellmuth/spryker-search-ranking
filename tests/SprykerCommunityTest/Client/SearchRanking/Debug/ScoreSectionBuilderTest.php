<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Debug;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
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
    /**
     * @return void
     */
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
    }

    /**
     * A product without a signal for some metric contributes 0 for it — the line still shows up, so the
     * displayed lines always account for the full total.
     *
     * @return void
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

    /**
     * @return void
     */
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
        $this->assertSame('|--> Normalized (score/(score+k))', $section['normalizedRelevanceLabel']);
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
     *
     * @return void
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
     * "random" is a noise-comparison metric, not a real business signal — kept last in the display order
     * regardless of where it was configured, so the metrics that actually explain the ranking read first.
     *
     * @return void
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

    /**
     * @return void
     */
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
}
