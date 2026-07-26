<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchRanking\Search\ShannonEntropyCalculator;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group ShannonEntropyCalculatorTest
 */
class ShannonEntropyCalculatorTest extends Unit
{
    /**
     * @return void
     */
    public function testUniformScoresReturnMaximumEntropy(): void
    {
        $calculator = new ShannonEntropyCalculator();

        $normalizedEntropy = $calculator->calculateNormalizedEntropy([5.0, 5.0, 5.0, 5.0, 5.0]);

        $this->assertEqualsWithDelta(1.0, $normalizedEntropy, 0.0001, 'All-equal scores carry no discriminating information — entropy should be maximal.');
    }

    /**
     * @return void
     */
    public function testOneDominantScoreReturnsNearZeroEntropy(): void
    {
        $calculator = new ShannonEntropyCalculator();

        $normalizedEntropy = $calculator->calculateNormalizedEntropy([1000.0, 0.01, 0.01, 0.01, 0.01]);

        $this->assertLessThan(0.05, $normalizedEntropy, 'A single overwhelmingly dominant score should carry almost all the relevance mass — entropy near 0.');
    }

    /**
     * @return void
     */
    public function testMixedScoresFallBetweenTheTwoExtremes(): void
    {
        $calculator = new ShannonEntropyCalculator();

        $normalizedEntropy = $calculator->calculateNormalizedEntropy([10.0, 5.0, 5.0, 1.0]);

        $this->assertGreaterThan(0.0, $normalizedEntropy);
        $this->assertLessThan(1.0, $normalizedEntropy);
    }

    /**
     * @return void
     */
    public function testFewerThanTwoScoresReturnZero(): void
    {
        $calculator = new ShannonEntropyCalculator();

        $this->assertSame(0.0, $calculator->calculateNormalizedEntropy([]), 'No scores at all: no distribution to measure.');
        $this->assertSame(0.0, $calculator->calculateNormalizedEntropy([42.0]), 'A single candidate carries no distribution to measure either.');
    }

    /**
     * @return void
     */
    public function testAllZeroScoresReturnZeroInsteadOfDividingByZero(): void
    {
        $calculator = new ShannonEntropyCalculator();

        $this->assertSame(0.0, $calculator->calculateNormalizedEntropy([0.0, 0.0, 0.0]));
    }

    /**
     * @return void
     */
    public function testAMixOfZeroAndNonZeroScoresDoesNotProduceNan(): void
    {
        $calculator = new ShannonEntropyCalculator();

        $normalizedEntropy = $calculator->calculateNormalizedEntropy([3.0, 0.0, 0.0]);

        $this->assertIsFloat($normalizedEntropy);
        $this->assertFalse(is_nan($normalizedEntropy), 'A zero score must be skipped (p*log(p) → 0 in the limit), never fed into log(0).');
    }
}
