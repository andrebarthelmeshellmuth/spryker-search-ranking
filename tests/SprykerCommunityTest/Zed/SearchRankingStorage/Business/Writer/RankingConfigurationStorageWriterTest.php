<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingStorage\Business\Writer;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRankingStorage\Business\Writer\RankingConfigurationStorageWriter;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingStorage
 * @group Business
 * @group Writer
 * @group RankingConfigurationStorageWriterTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRankingStorage\SearchRankingStorageZedTester $tester
 */
class RankingConfigurationStorageWriterTest extends Unit
{
    /**
     * @return void
     */
    public function testNormalizesActiveMetricWeightsToSumToOneBeforePublishing(): void
    {
        // Arrange
        $searchRankingFacadeMock = $this->createSearchRankingFacadeMock([
            $this->createMetricTransfer('top_seller', 2.0),
            $this->createMetricTransfer('pdp_impressions', 2.0),
        ], 0.6, 12.0);

        $capturedConfiguration = null;
        $entityManagerMock = $this->createMock(SearchRankingStorageEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveRankingConfiguration')
            ->willReturnCallback(function (array $configurationData) use (&$capturedConfiguration): void {
                $capturedConfiguration = $configurationData;
            });

        $writer = $this->createWriter($searchRankingFacadeMock, $entityManagerMock);

        // Act
        $writer->publishRankingConfiguration();

        // Assert
        $this->assertSame(['top_seller' => 0.5, 'pdp_impressions' => 0.5], $capturedConfiguration['metric_weights']);
        $this->assertSame(0.6, $capturedConfiguration['relevance_weight']);
        $this->assertSame(12.0, $capturedConfiguration['relevance_saturation_point']);
    }

    /**
     * A single active metric must normalize to exactly 1.0 regardless of its raw weight — the safety net
     * that guarantees the business-signal term of the ranking formula never exceeds 1 must not be
     * bypassable by having only one active metric with e.g. weight 0.3 stay at 0.3.
     *
     * @return void
     */
    public function testNormalizesASingleActiveMetricToExactlyOneRegardlessOfItsRawWeight(): void
    {
        // Arrange
        $searchRankingFacadeMock = $this->createSearchRankingFacadeMock([
            $this->createMetricTransfer('top_seller', 0.3),
        ], 0.6, 12.0);

        $capturedConfiguration = null;
        $entityManagerMock = $this->createMock(SearchRankingStorageEntityManagerInterface::class);
        $entityManagerMock->method('saveRankingConfiguration')
            ->willReturnCallback(function (array $configurationData) use (&$capturedConfiguration): void {
                $capturedConfiguration = $configurationData;
            });

        $writer = $this->createWriter($searchRankingFacadeMock, $entityManagerMock);

        // Act
        $writer->publishRankingConfiguration();

        // Assert
        $this->assertSame(['top_seller' => 1.0], $capturedConfiguration['metric_weights']);
    }

    /**
     * Weights already summing to 1 must come out unchanged (aside from float rounding).
     *
     * @return void
     */
    public function testLeavesAlreadyNormalizedWeightsUnchanged(): void
    {
        // Arrange
        $searchRankingFacadeMock = $this->createSearchRankingFacadeMock([
            $this->createMetricTransfer('top_seller', 0.5),
            $this->createMetricTransfer('pdp_impressions', 0.5),
        ], 0.6, 12.0);

        $capturedConfiguration = null;
        $entityManagerMock = $this->createMock(SearchRankingStorageEntityManagerInterface::class);
        $entityManagerMock->method('saveRankingConfiguration')
            ->willReturnCallback(function (array $configurationData) use (&$capturedConfiguration): void {
                $capturedConfiguration = $configurationData;
            });

        $writer = $this->createWriter($searchRankingFacadeMock, $entityManagerMock);

        // Act
        $writer->publishRankingConfiguration();

        // Assert
        $this->assertSame(['top_seller' => 0.5, 'pdp_impressions' => 0.5], $capturedConfiguration['metric_weights']);
    }

    /**
     * All-zero (or no) active weights must be left as-is rather than dividing by zero — FunctionScoreBuilder
     * already treats an all-zero/empty weight map as "no usable business signal".
     *
     * @return void
     */
    public function testLeavesAllZeroWeightsUnchangedInsteadOfDividingByZero(): void
    {
        // Arrange
        $searchRankingFacadeMock = $this->createSearchRankingFacadeMock([
            $this->createMetricTransfer('top_seller', 0.0),
            $this->createMetricTransfer('pdp_impressions', 0.0),
        ], 0.6, 12.0);

        $capturedConfiguration = null;
        $entityManagerMock = $this->createMock(SearchRankingStorageEntityManagerInterface::class);
        $entityManagerMock->method('saveRankingConfiguration')
            ->willReturnCallback(function (array $configurationData) use (&$capturedConfiguration): void {
                $capturedConfiguration = $configurationData;
            });

        $writer = $this->createWriter($searchRankingFacadeMock, $entityManagerMock);

        // Act
        $writer->publishRankingConfiguration();

        // Assert
        $this->assertSame(['top_seller' => 0.0, 'pdp_impressions' => 0.0], $capturedConfiguration['metric_weights']);
    }

    /**
     * With zero active metrics at all `$metricWeights` is `[]`, not just all-zero — `array_sum([])`
     * returns an int `0`, not a float `0.0`, so this exercises the same zero-guard through its other
     * (empty-array) branch, not only the all-zero-values branch above.
     *
     * @return void
     */
    public function testLeavesMetricWeightsEmptyWhenThereAreNoActiveMetricsAtAll(): void
    {
        // Arrange
        $searchRankingFacadeMock = $this->createSearchRankingFacadeMock([], 0.6, 12.0);

        $capturedConfiguration = null;
        $entityManagerMock = $this->createMock(SearchRankingStorageEntityManagerInterface::class);
        $entityManagerMock->method('saveRankingConfiguration')
            ->willReturnCallback(function (array $configurationData) use (&$capturedConfiguration): void {
                $capturedConfiguration = $configurationData;
            });

        $writer = $this->createWriter($searchRankingFacadeMock, $entityManagerMock);

        // Act
        $writer->publishRankingConfiguration();

        // Assert
        $this->assertSame([], $capturedConfiguration['metric_weights']);
    }

    /**
     * @return void
     */
    public function testFlushesTheSynchronizationBufferAfterSaving(): void
    {
        // Arrange
        $searchRankingFacadeMock = $this->createSearchRankingFacadeMock([], 0.6, 12.0);
        $entityManagerMock = $this->createMock(SearchRankingStorageEntityManagerInterface::class);

        $synchronizationFacadeMock = $this->createMock(SearchRankingStorageToSynchronizationFacadeInterface::class);
        $synchronizationFacadeMock->expects($this->once())->method('flushSynchronizationMessagesFromBuffer');

        $writer = new RankingConfigurationStorageWriter($searchRankingFacadeMock, $entityManagerMock, $synchronizationFacadeMock);

        // Act
        $writer->publishRankingConfiguration();
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchRankingMetricTransfer> $metricTransfers
     * @param float $relevanceWeight
     * @param float $relevanceSaturationPoint
     *
     * @return \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface
     */
    protected function createSearchRankingFacadeMock(
        array $metricTransfers,
        float $relevanceWeight,
        float $relevanceSaturationPoint,
    ): SearchRankingStorageToSearchRankingFacadeInterface {
        $collectionTransfer = new SearchRankingMetricCollectionTransfer();

        foreach ($metricTransfers as $metricTransfer) {
            $collectionTransfer->addMetric($metricTransfer);
        }

        $searchRankingFacadeMock = $this->createMock(SearchRankingStorageToSearchRankingFacadeInterface::class);
        $searchRankingFacadeMock->method('getActiveMetricCollection')->willReturn($collectionTransfer);
        $searchRankingFacadeMock->method('getRelevanceWeight')->willReturn($relevanceWeight);
        $searchRankingFacadeMock->method('getRelevanceSaturationPoint')->willReturn($relevanceSaturationPoint);

        return $searchRankingFacadeMock;
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface $entityManager
     *
     * @return \SprykerCommunity\Zed\SearchRankingStorage\Business\Writer\RankingConfigurationStorageWriter
     */
    protected function createWriter(
        SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade,
        SearchRankingStorageEntityManagerInterface $entityManager,
    ): RankingConfigurationStorageWriter {
        return new RankingConfigurationStorageWriter(
            $searchRankingFacade,
            $entityManager,
            $this->createMock(SearchRankingStorageToSynchronizationFacadeInterface::class),
        );
    }

    /**
     * @param string $name
     * @param float $weight
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    protected function createMetricTransfer(string $name, float $weight): SearchRankingMetricTransfer
    {
        return (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(1)
            ->setName($name)
            ->setWeight($weight)
            ->setFormula('x')
            ->setIsActive(true);
    }
}
