<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Metric;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\WeightNormalizer;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Metric
 * @group WeightNormalizerTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class WeightNormalizerTest extends Unit
{
    public function testDividesEachActiveWeightByTheSumOfAllOfThemAndPersists(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([
            $this->createMetricTransfer(1, 2.0),
            $this->createMetricTransfer(2, 2.0),
        ]);

        $capturedWeights = [];
        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->exactly(2))
            ->method('saveMetricWeight')
            ->willReturnCallback(function (int $idSearchRankingMetric, string $storeName, string $localeName, float $weight) use (&$capturedWeights): void {
                $this->assertSame('DE', $storeName);
                $this->assertSame('de_DE', $localeName);
                $capturedWeights[$idSearchRankingMetric] = $weight;
            });

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $metricWriterMock))->normalizeActiveWeights('DE', 'de_DE');

        // Assert
        $this->assertTrue($wasNormalized);
        $this->assertSame([1 => 0.5, 2 => 0.5], $capturedWeights);
    }

    /**
     * A single active metric must normalize to exactly 1.0 regardless of its raw weight — it is 100% of
     * the active-weight sum either way.
     */
    public function testNormalizesASingleActiveMetricToExactlyOneRegardlessOfItsRawWeight(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([
            $this->createMetricTransfer(5, 0.3),
        ]);

        $capturedWeights = [];
        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->method('saveMetricWeight')
            ->willReturnCallback(function (int $idSearchRankingMetric, string $storeName, string $localeName, float $weight) use (&$capturedWeights): void {
                $this->assertSame('DE', $storeName);
                $this->assertSame('de_DE', $localeName);
                $capturedWeights[$idSearchRankingMetric] = $weight;
            });

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $metricWriterMock))->normalizeActiveWeights('DE', 'de_DE');

        // Assert
        $this->assertTrue($wasNormalized);
        $this->assertSame([5 => 1.0], $capturedWeights);
    }

    public function testIsANoOpWhenActiveWeightsAlreadySumToOne(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([
            $this->createMetricTransfer(1, 0.5),
            $this->createMetricTransfer(2, 0.5),
        ]);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->never())->method('saveMetricWeight');

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $metricWriterMock))->normalizeActiveWeights('DE', 'de_DE');

        // Assert
        $this->assertFalse($wasNormalized);
    }

    public function testIsANoOpWhenAllActiveWeightsAreZero(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([
            $this->createMetricTransfer(1, 0.0),
            $this->createMetricTransfer(2, 0.0),
        ]);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->never())->method('saveMetricWeight');

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $metricWriterMock))->normalizeActiveWeights('DE', 'de_DE');

        // Assert
        $this->assertFalse($wasNormalized);
    }

    public function testIsANoOpWhenThereAreNoActiveMetricsAtAll(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([]);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->never())->method('saveMetricWeight');

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $metricWriterMock))->normalizeActiveWeights('DE', 'de_DE');

        // Assert
        $this->assertFalse($wasNormalized);
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchRankingMetricTransfer> $metricTransfers
     */
    protected function createRepositoryMock(array $metricTransfers): SearchRankingRepositoryInterface
    {
        $collectionTransfer = new SearchRankingMetricCollectionTransfer();

        foreach ($metricTransfers as $metricTransfer) {
            $collectionTransfer->addMetric($metricTransfer);
        }

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getActiveMetricCollection')->willReturn($collectionTransfer);

        return $repositoryMock;
    }

    /**
     * @param int $idSearchRankingMetric
     * @param float $weight
     */
    protected function createMetricTransfer(int $idSearchRankingMetric, float $weight): SearchRankingMetricTransfer
    {
        return (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric($idSearchRankingMetric)
            ->setName('metric_' . $idSearchRankingMetric)
            ->setWeight($weight)
            ->setFormula('x')
            ->setIsActive(true);
    }
}
