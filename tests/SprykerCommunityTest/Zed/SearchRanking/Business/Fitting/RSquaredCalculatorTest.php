<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Fitting;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\RSquaredCalculator;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Fitting
 * @group RSquaredCalculatorTest
 * Add your own group annotations below this line
 */
class RSquaredCalculatorTest extends Unit
{
    public function testReturnsExactlyOneForAPerfectPrediction(): void
    {
        // Arrange
        $calculator = new RSquaredCalculator();

        // Act
        $rSquared = $calculator->calculate([0.0, 0.25, 0.5, 0.75, 1.0], [0.0, 0.25, 0.5, 0.75, 1.0]);

        // Assert
        $this->assertSame(1.0, $rSquared);
    }

    /**
     * A prediction that is always exactly the mean of the actual values explains none of the variance —
     * R² = 0 by definition, the baseline every real fit is judged against.
     */
    public function testReturnsZeroWhenThePredictionIsAlwaysTheMean(): void
    {
        // Arrange
        $calculator = new RSquaredCalculator();

        // Act
        $rSquared = $calculator->calculate([0.0, 0.5, 1.0], [0.5, 0.5, 0.5]);

        // Assert
        $this->assertSame(0.0, $rSquared);
    }

    public function testReturnsNullWhenArrayLengthsDiffer(): void
    {
        // Arrange
        $calculator = new RSquaredCalculator();

        // Act
        $rSquared = $calculator->calculate([0.0, 0.5, 1.0], [0.0, 1.0]);

        // Assert
        $this->assertNull($rSquared);
    }

    public function testReturnsNullWhenAPredictedValueIsNonFinite(): void
    {
        // Arrange
        $calculator = new RSquaredCalculator();

        // Act
        $rSquared = $calculator->calculate([0.0, 0.5, 1.0], [0.0, INF, 1.0]);

        // Assert
        $this->assertNull($rSquared);
    }

    /**
     * A single-point comparison has zero variance to explain (SS_tot = 0) — an exact match still reports
     * a perfect fit rather than dividing by zero.
     */
    public function testReturnsOneForAnExactSinglePointMatchDespiteZeroVariance(): void
    {
        // Arrange
        $calculator = new RSquaredCalculator();

        // Act
        $rSquared = $calculator->calculate([0.5], [0.5]);

        // Assert
        $this->assertSame(1.0, $rSquared);
    }
}
