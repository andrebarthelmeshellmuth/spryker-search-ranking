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
    public function testBuildsOneLinePerMetricPlusFloorAndTotal(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5, 'pdp_impressions' => 0.3])
            ->setScoreFloor(0.5);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, [
            'top_seller' => 0.5099,
            'pdp_impressions' => 0.2033,
        ], null);

        // Assert
        $this->assertSame('Business signals', $section['title']);
        $this->assertCount(3, $section['lines']);

        $this->assertSame('top_seller', $section['lines'][0]['label']);
        $this->assertSame('0.5099 × 0.50', $section['lines'][0]['calculation']);
        $this->assertEqualsWithDelta(0.25495, $section['lines'][0]['value'], 1.0E-9);

        $this->assertSame('score floor', $section['lines'][2]['label']);
        $this->assertSame(0.5, $section['lines'][2]['value']);

        $this->assertSame('Business signal total', $section['summaryLabel']);
        $this->assertEqualsWithDelta(0.5 + 0.25495 + 0.06099, $section['summaryValue'], 1.0E-9);
        $this->assertArrayNotHasKey('formula', $section);
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
            ->setScoreFloor(0.5);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, [], null);

        // Assert
        $this->assertSame('0.0000 × 0.50', $section['lines'][0]['calculation']);
        $this->assertSame(0.0, $section['lines'][0]['value']);
        $this->assertSame(0.5, $section['summaryValue']);
    }

    /**
     * @return void
     */
    public function testAddsTheCombinationFormulaWhenTheQueryScoreIsKnown(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5])
            ->setScoreFloor(0.5);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, ['top_seller' => 0.5], 6.9244);

        // Assert: (1 + sqrt(6.9244)) * 0.75 = 2.7236 (4 decimals)
        $this->assertSame('(1 + √6.9244) × 0.7500 = 2.7236', $section['formula']);
    }

    /**
     * @return void
     */
    public function testReturnsNullWhenNoMetricWeightsAreConfigured(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights([])
            ->setScoreFloor(0.5);

        // Act
        $section = (new ScoreSectionBuilder())->build($configurationTransfer, ['top_seller' => 0.5], 1.0);

        // Assert
        $this->assertNull($section);
    }
}
