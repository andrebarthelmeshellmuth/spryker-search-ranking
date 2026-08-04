<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Digest;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\StoreTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilder;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * `buildDigest()` takes a plain array and returns a transfer with no persistence access at all, so it is
 * exercised directly. `rebuildDigest()`/`rebuildDigests()`
 * only orchestrate calls against `SearchRankingRepositoryInterface`/`SearchRankingEntityManagerInterface`
 * — both plain interfaces — so they are exercised with mocks below rather than left to integration
 * coverage.
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
     * a single-value edge case.
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
     * A metric with no raw values yet (e.g. freshly created, cron hasn't collected anything) must be
     * skipped rather than persisted as an empty/bogus digest.
     */
    public function testRebuildDigestReturnsFalseAndSavesNothingWhenNoRawValuesExist(): void
    {
        // Arrange
        $repository = $this->createMock(SearchRankingRepositoryInterface::class);
        $repository->method('getRawValues')
            ->with(7, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
            ->willReturn([]);

        $entityManager = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManager->expects($this->never())->method('saveMetricDigest');

        $builder = new MetricDigestBuilder($repository, $entityManager, $this->createStoreFacadeMock());

        // Act
        $result = $builder->rebuildDigest(7, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME);

        // Assert
        $this->assertFalse($result);
    }

    public function testRebuildDigestBuildsAndSavesADigestAttributedToTheGivenMetric(): void
    {
        // Arrange
        $repository = $this->createMock(SearchRankingRepositoryInterface::class);
        $repository->method('getRawValues')
            ->with(7, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
            ->willReturn([10.0, 20.0, 30.0]);

        $savedDigestTransfer = null;

        $entityManager = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('saveMetricDigest')
            ->willReturnCallback(function (SearchRankingMetricDigestTransfer $digestTransfer) use (&$savedDigestTransfer) {
                $savedDigestTransfer = $digestTransfer;

                return $digestTransfer;
            });

        $builder = new MetricDigestBuilder($repository, $entityManager, $this->createStoreFacadeMock());

        // Act
        $result = $builder->rebuildDigest(7, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME);

        // Assert
        $this->assertTrue($result);
        $this->assertSame(7, $savedDigestTransfer->getFkSearchRankingMetric());
        $this->assertSame(20.0, $savedDigestTransfer->getMeanValue());
    }

    public function testRebuildDigestsProcessesOnlyMetricsWithRawValuesAndCountsThem(): void
    {
        // Arrange
        $metricWithData = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(1);
        $metricWithoutData = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(2);

        $repository = $this->createMock(SearchRankingRepositoryInterface::class);
        $repository->method('getActiveMetricCollection')->willReturn(
            (new SearchRankingMetricCollectionTransfer())->addMetric($metricWithData)->addMetric($metricWithoutData),
        );
        $repository->method('getRawValues')->willReturnMap([
            [1, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, [5.0, 15.0]],
            [2, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, []],
        ]);

        $entityManager = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManager->expects($this->once())->method('saveMetricDigest');

        $builder = new MetricDigestBuilder($repository, $entityManager, $this->createStoreFacadeMock());

        // Act
        $processedCount = $builder->rebuildDigests();

        // Assert
        $this->assertSame(1, $processedCount);
    }

    protected function createDigestBuilder(): MetricDigestBuilder
    {
        return new MetricDigestBuilder(
            $this->createMock(SearchRankingRepositoryInterface::class),
            $this->createMock(SearchRankingEntityManagerInterface::class),
            $this->createStoreFacadeMock(),
        );
    }

    protected function createStoreFacadeMock(): SearchRankingToStoreFacadeInterface
    {
        $storeFacadeMock = $this->createMock(SearchRankingToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())
                ->setName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
                ->setAvailableLocaleIsoCodes([SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME]),
        ]);

        return $storeFacadeMock;
    }
}
