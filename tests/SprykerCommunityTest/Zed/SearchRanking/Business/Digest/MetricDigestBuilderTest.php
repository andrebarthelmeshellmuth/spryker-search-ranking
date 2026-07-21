<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Digest;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilder;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * `rebuildDigest()`/`rebuildDigests()` need a real repository/entity manager and are left to integration
 * coverage — but `buildDigest()` takes a plain array and returns a transfer with no persistence access at
 * all, so it is exercised directly here, the same shape as `StatisticsCalculatorTest`.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Digest
 * @group MetricDigestBuilderTest
 * Add your own group annotations below this line
 */
class MetricDigestBuilderTest extends Unit
{
    /**
     * @return void
     */
    public function testComputesMinMaxMeanMedianAndSampleCount(): void
    {
        // Arrange
        $builder = $this->createDigestBuilder();

        // Act
        $digestTransfer = $builder->buildDigest([10.0, 20.0, 30.0, 40.0, 50.0]);

        // Assert
        $this->assertSame(5, $digestTransfer->getSampleCount());
        $this->assertSame(10.0, $digestTransfer->getMinValue());
        $this->assertSame(50.0, $digestTransfer->getMaxValue());
        $this->assertSame(30.0, $digestTransfer->getMeanValue());
        $this->assertSame(30.0, $digestTransfer->getMedianValue());
    }

    /**
     * The percentile backbone must have exactly 101 points (0, 1, 2, ..., 100), regardless of how many
     * raw values were given.
     *
     * @return void
     */
    public function testPercentileBackboneHasExactlyOneHundredAndOneEntries(): void
    {
        // Arrange
        $builder = $this->createDigestBuilder();

        // Act
        $digestTransfer = $builder->buildDigest([10.0, 20.0, 30.0, 40.0, 50.0]);

        // Assert
        $this->assertCount(101, $digestTransfer->getPercentiles());
    }

    /**
     * percentiles[0] is the p0 (minimum) point, percentiles[100] is the p100 (maximum) point, and
     * percentiles[50] is the median — all three must agree exactly with the corresponding top-level stat.
     *
     * @return void
     */
    public function testFirstMiddleAndLastPercentileMatchMinMedianAndMax(): void
    {
        // Arrange
        $builder = $this->createDigestBuilder();

        // Act
        $digestTransfer = $builder->buildDigest([10.0, 20.0, 30.0, 40.0, 50.0]);
        $percentiles = $digestTransfer->getPercentiles();

        // Assert
        $this->assertSame($digestTransfer->getMinValue(), $percentiles[0]);
        $this->assertSame($digestTransfer->getMedianValue(), $percentiles[50]);
        $this->assertSame($digestTransfer->getMaxValue(), $percentiles[100]);
    }

    /**
     * A single-element sample has no interpolation range — every statistic collapses onto that one value,
     * same as StatisticsCalculator's equivalent edge case.
     *
     * @return void
     */
    public function testASingleRawValueMakesEveryStatisticEqualToItself(): void
    {
        // Arrange
        $builder = $this->createDigestBuilder();

        // Act
        $digestTransfer = $builder->buildDigest([7.5]);

        // Assert
        $this->assertSame(1, $digestTransfer->getSampleCount());
        $this->assertSame(7.5, $digestTransfer->getMinValue());
        $this->assertSame(7.5, $digestTransfer->getMaxValue());
        $this->assertSame(7.5, $digestTransfer->getMeanValue());
        $this->assertSame(7.5, $digestTransfer->getMedianValue());
        $this->assertSame(7.5, $digestTransfer->getPercentiles()[0]);
        $this->assertSame(7.5, $digestTransfer->getPercentiles()[100]);
    }

    /**
     * Raw values must be sorted internally before computing statistics — scrambled input must produce
     * the identical digest as already-sorted input.
     *
     * @return void
     */
    public function testResultIsIndependentOfInputOrder(): void
    {
        // Arrange
        $builder = $this->createDigestBuilder();

        // Act
        $sortedResult = $builder->buildDigest([1.0, 2.0, 3.0, 4.0, 5.0]);
        $scrambledResult = $builder->buildDigest([3.0, 1.0, 5.0, 2.0, 4.0]);

        // Assert
        $this->assertSame($sortedResult->getMinValue(), $scrambledResult->getMinValue());
        $this->assertSame($sortedResult->getMaxValue(), $scrambledResult->getMaxValue());
        $this->assertSame($sortedResult->getMedianValue(), $scrambledResult->getMedianValue());
        $this->assertSame($sortedResult->getPercentiles(), $scrambledResult->getPercentiles());
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilder
     */
    protected function createDigestBuilder(): MetricDigestBuilder
    {
        return new MetricDigestBuilder(
            $this->createMock(SearchRankingRepositoryInterface::class),
            $this->createMock(SearchRankingEntityManagerInterface::class),
        );
    }
}
