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
use SprykerCommunity\Zed\SearchRanking\Business\Metric\WeightNormalizer;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
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
    /**
     * @return void
     */
    public function testDividesEachActiveWeightByTheSumOfAllOfThemAndPersists(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([
            $this->createMetricTransfer(1, 2.0),
            $this->createMetricTransfer(2, 2.0),
        ]);

        $capturedWeights = null;
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('updateMetricWeights')
            ->willReturnCallback(function (array $weightsById) use (&$capturedWeights): void {
                $capturedWeights = $weightsById;
            });

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $entityManagerMock))->normalizeActiveWeights();

        // Assert
        $this->assertTrue($wasNormalized);
        $this->assertSame([1 => 0.5, 2 => 0.5], $capturedWeights);
    }

    /**
     * A single active metric must normalize to exactly 1.0 regardless of its raw weight — it is 100% of
     * the active-weight sum either way.
     *
     * @return void
     */
    public function testNormalizesASingleActiveMetricToExactlyOneRegardlessOfItsRawWeight(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([
            $this->createMetricTransfer(5, 0.3),
        ]);

        $capturedWeights = null;
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('updateMetricWeights')
            ->willReturnCallback(function (array $weightsById) use (&$capturedWeights): void {
                $capturedWeights = $weightsById;
            });

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $entityManagerMock))->normalizeActiveWeights();

        // Assert
        $this->assertTrue($wasNormalized);
        $this->assertSame([5 => 1.0], $capturedWeights);
    }

    /**
     * @return void
     */
    public function testIsANoOpWhenActiveWeightsAlreadySumToOne(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([
            $this->createMetricTransfer(1, 0.5),
            $this->createMetricTransfer(2, 0.5),
        ]);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('updateMetricWeights');

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $entityManagerMock))->normalizeActiveWeights();

        // Assert
        $this->assertFalse($wasNormalized);
    }

    /**
     * @return void
     */
    public function testIsANoOpWhenAllActiveWeightsAreZero(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([
            $this->createMetricTransfer(1, 0.0),
            $this->createMetricTransfer(2, 0.0),
        ]);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('updateMetricWeights');

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $entityManagerMock))->normalizeActiveWeights();

        // Assert
        $this->assertFalse($wasNormalized);
    }

    /**
     * @return void
     */
    public function testIsANoOpWhenThereAreNoActiveMetricsAtAll(): void
    {
        // Arrange
        $repositoryMock = $this->createRepositoryMock([]);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('updateMetricWeights');

        // Act
        $wasNormalized = (new WeightNormalizer($repositoryMock, $entityManagerMock))->normalizeActiveWeights();

        // Assert
        $this->assertFalse($wasNormalized);
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchRankingMetricTransfer> $metricTransfers
     *
     * @return \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface
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
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
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
