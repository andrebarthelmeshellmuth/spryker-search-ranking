<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\RandomImpact;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Client\SearchRanking\RandomImpact\RandomImpactCalculator;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group RandomImpact
 * @group RandomImpactCalculatorTest
 * @group Portable
 */
class RandomImpactCalculatorTest extends Unit
{
    public function testIsActiveIsFalseWhenRandomMetricNameIsUnset(): void
    {
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['random' => 0.1]);

        $this->assertFalse($calculator->isActive($configurationTransfer));
    }

    public function testIsActiveIsFalseWhenTheRandomMetricIsNotAmongTheActiveWeights(): void
    {
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setMetricWeights(['top_seller' => 1.0]);

        $this->assertFalse($calculator->isActive($configurationTransfer));
    }

    public function testIsActiveIsFalseWhenTheRandomMetricsWeightIsZero(): void
    {
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setMetricWeights(['random' => 0.0, 'top_seller' => 1.0]);

        $this->assertFalse($calculator->isActive($configurationTransfer));
    }

    public function testIsActiveIsTrueWhenTheRandomMetricHasAPositiveWeight(): void
    {
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setMetricWeights(['random' => 0.1, 'top_seller' => 0.9]);

        $this->assertTrue($calculator->isActive($configurationTransfer));
    }

    public function testCalculateReturnsEmptyWhenNotActive(): void
    {
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setMetricWeights(['random' => 0.0]);

        $hits = [
            ['idProductAbstract' => 1, 'score' => 0.9, 'randomSignal' => 1.0],
            ['idProductAbstract' => 2, 'score' => 0.5, 'randomSignal' => 0.0],
        ];

        $this->assertSame([], $calculator->calculate($hits, $configurationTransfer));
    }

    public function testCalculateReturnsEmptyForFewerThanTwoHitsEvenWhenActive(): void
    {
        // Arrange -- a single hit has no other position to move relative to.
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setRelevanceWeight(0.75)
            ->setMetricWeights(['random' => 0.2]);

        $hits = [
            ['idProductAbstract' => 1, 'score' => 0.9, 'randomSignal' => 1.0],
        ];

        $this->assertSame([], $calculator->calculate($hits, $configurationTransfer));
    }

    /**
     * relevanceWeight=0.75, random weight=0.2 -> businessSignalShare * randomWeight = 0.25 * 0.2 = 0.05
     * per unit of randomSignal. Product 1 (live position 1) has randomSignal=1.0, losing 0.05 -> new
     * score 0.90 - 0.05 = 0.85. Product 2 (live position 2) has randomSignal=0.0, losing nothing -> stays
     * at 0.86. 0.86 > 0.85, so product 2 overtakes product 1: product 1 moves DOWN (worse, +1, red),
     * product 2 moves UP (better, -1, green).
     */
    public function testSubtractsOnlyTheRandomMetricsOwnWeightedContributionAndReordersAccordingly(): void
    {
        // Arrange
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setRelevanceWeight(0.75)
            ->setMetricWeights(['random' => 0.2, 'top_seller' => 0.8]);

        $hits = [
            ['idProductAbstract' => 1, 'score' => 0.90, 'randomSignal' => 1.0],
            ['idProductAbstract' => 2, 'score' => 0.86, 'randomSignal' => 0.0],
        ];

        // Act
        $deltas = $calculator->calculate($hits, $configurationTransfer);

        // Assert
        $this->assertSame(1, $deltas[1], 'Product 1 falls from position 1 to position 2 -- worse, positive, red.');
        $this->assertSame(-1, $deltas[2], 'Product 2 rises from position 2 to position 1 -- better, negative, green.');
    }

    public function testOmitsAProductWhosePositionDoesNotChange(): void
    {
        // Arrange -- product 1's own randomSignal is 0, so removing random's contribution can't move it
        // at all; product 2/3 swap between themselves.
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setRelevanceWeight(0.75)
            ->setMetricWeights(['random' => 0.2, 'top_seller' => 0.8]);

        $hits = [
            ['idProductAbstract' => 1, 'score' => 0.95, 'randomSignal' => 0.0],
            ['idProductAbstract' => 2, 'score' => 0.90, 'randomSignal' => 1.0],
            ['idProductAbstract' => 3, 'score' => 0.86, 'randomSignal' => 0.0],
        ];

        // Act
        $deltas = $calculator->calculate($hits, $configurationTransfer);

        // Assert
        $this->assertArrayNotHasKey(1, $deltas, 'Position 1 is unreachable either way -- must be omitted, not present with delta 0.');
        $this->assertSame(1, $deltas[2]);
        $this->assertSame(-1, $deltas[3]);
    }

    public function testAProductWithNoRandomSignalOfItsOwnIsTreatedAsZero(): void
    {
        // Arrange -- a product missing its own `scores.random` field entirely (never scored for that
        // metric) must be treated exactly like randomSignal=0.0, not error or skew the simulation.
        $calculator = new RandomImpactCalculator();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setRelevanceWeight(0.75)
            ->setMetricWeights(['random' => 0.2]);

        $hits = [
            ['idProductAbstract' => 1, 'score' => 0.90, 'randomSignal' => 0.0],
            ['idProductAbstract' => 2, 'score' => 0.86, 'randomSignal' => 0.0],
        ];

        // Act
        $deltas = $calculator->calculate($hits, $configurationTransfer);

        // Assert
        $this->assertSame([], $deltas, 'Neither hit carries a random contribution to remove -- order is unchanged.');
    }
}
