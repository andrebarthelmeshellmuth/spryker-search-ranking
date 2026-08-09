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
use Generated\Shared\Transfer\StoreTransfer;
use SprykerCommunity\Zed\SearchRankingStorage\Business\Writer\RankingConfigurationStorageWriter;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToStoreFacadeInterface;
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
        $this->assertSame(0.7, $capturedConfiguration['specificity_blend_weight']);
        $this->assertSame(3.0, $capturedConfiguration['specificity_saturation_point']);
        $this->assertSame(2.0, $capturedConfiguration['specificity_curve_exponent']);
        $this->assertSame(1.0, $capturedConfiguration['specificity_weight_exponent']);
        $this->assertSame(0.5, $capturedConfiguration['specificity_weight_shift_magnitude']);
        $this->assertSame('random', $capturedConfiguration['random_metric_name']);
    }

    /**
     * A single active metric must normalize to exactly 1.0 regardless of its raw weight — the safety net
     * that guarantees the business-signal term of the ranking formula never exceeds 1 must not be
     * bypassable by having only one active metric with e.g. weight 0.3 stay at 0.3.
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

    public function testFlushesTheSynchronizationBufferAfterSaving(): void
    {
        // Arrange
        $searchRankingFacadeMock = $this->createSearchRankingFacadeMock([], 0.6, 12.0);
        $entityManagerMock = $this->createMock(SearchRankingStorageEntityManagerInterface::class);

        $synchronizationFacadeMock = $this->createMock(SearchRankingStorageToSynchronizationFacadeInterface::class);
        $synchronizationFacadeMock->expects($this->once())->method('flushSynchronizationMessagesFromBuffer');

        $writer = new RankingConfigurationStorageWriter(
            $searchRankingFacadeMock,
            $entityManagerMock,
            $synchronizationFacadeMock,
            $this->createStoreFacadeMock(),
        );

        // Act
        $writer->publishRankingConfiguration();
    }

    /**
     * Publishing must fan out over every store × that store's own available locales, saving one document
     * per scope with the SAME scope threaded through to both `getActiveMetricCollection()`/the other
     * getters AND `saveRankingConfiguration()` — never a cross-join against every globally available
     * locale (mirrors `ProductMetricNormalizer`'s own fan-out shape) — and flushing the synchronization
     * buffer exactly once at the end, not once per scope.
     */
    public function testPublishesOneConfigurationDocumentPerStoreAndItsOwnLocales(): void
    {
        // Arrange
        $searchRankingFacadeMock = $this->createMock(SearchRankingStorageToSearchRankingFacadeInterface::class);
        $searchRankingFacadeMock->method('getActiveMetricCollection')->willReturn(new SearchRankingMetricCollectionTransfer());
        $searchRankingFacadeMock->method('getRelevanceWeight')->willReturnMap([
            ['DE', 'de_DE', 0.6],
            ['AT', 'de_AT', 0.9],
            ['AT', 'fr_FR', 0.9],
        ]);
        $searchRankingFacadeMock->method('getRelevanceSaturationPoint')->willReturn(12.0);
        $searchRankingFacadeMock->method('getSpecificityBlendWeight')->willReturn(0.7);
        $searchRankingFacadeMock->method('getSpecificitySaturationPoint')->willReturn(3.0);
        $searchRankingFacadeMock->method('getSpecificityCurveExponent')->willReturn(2.0);
        $searchRankingFacadeMock->method('getSpecificityWeightExponent')->willReturn(1.0);
        $searchRankingFacadeMock->method('getSpecificityWeightShiftMagnitude')->willReturn(0.5);
        $searchRankingFacadeMock->method('getRandomMetricName')->willReturn('random');

        $capturedScopes = [];
        $entityManagerMock = $this->createMock(SearchRankingStorageEntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(3))
            ->method('saveRankingConfiguration')
            ->willReturnCallback(function (array $configurationData, string $storeName, string $localeName) use (&$capturedScopes): void {
                $capturedScopes[] = [$storeName, $localeName, $configurationData['relevance_weight']];
            });

        $synchronizationFacadeMock = $this->createMock(SearchRankingStorageToSynchronizationFacadeInterface::class);
        $synchronizationFacadeMock->expects($this->once())->method('flushSynchronizationMessagesFromBuffer');

        $storeFacadeMock = $this->createMock(SearchRankingStorageToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())->setName('DE')->setAvailableLocaleIsoCodes(['de_DE']),
            (new StoreTransfer())->setName('AT')->setAvailableLocaleIsoCodes(['de_AT', 'fr_FR']),
        ]);

        $writer = new RankingConfigurationStorageWriter(
            $searchRankingFacadeMock,
            $entityManagerMock,
            $synchronizationFacadeMock,
            $storeFacadeMock,
        );

        // Act
        $writer->publishRankingConfiguration();

        // Assert
        $this->assertSame([
            ['DE', 'de_DE', 0.6],
            ['AT', 'de_AT', 0.9],
            ['AT', 'fr_FR', 0.9],
        ], $capturedScopes);
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchRankingMetricTransfer> $metricTransfers
     * @param float $relevanceWeight
     * @param float $relevanceSaturationPoint
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
        $searchRankingFacadeMock->method('getSpecificityBlendWeight')->willReturn(0.7);
        $searchRankingFacadeMock->method('getSpecificitySaturationPoint')->willReturn(3.0);
        $searchRankingFacadeMock->method('getSpecificityCurveExponent')->willReturn(2.0);
        $searchRankingFacadeMock->method('getSpecificityWeightExponent')->willReturn(1.0);
        $searchRankingFacadeMock->method('getSpecificityWeightShiftMagnitude')->willReturn(0.5);
        $searchRankingFacadeMock->method('getRandomMetricName')->willReturn('random');

        return $searchRankingFacadeMock;
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface $entityManager
     */
    protected function createWriter(
        SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade,
        SearchRankingStorageEntityManagerInterface $entityManager,
    ): RankingConfigurationStorageWriter {
        return new RankingConfigurationStorageWriter(
            $searchRankingFacade,
            $entityManager,
            $this->createMock(SearchRankingStorageToSynchronizationFacadeInterface::class),
            $this->createStoreFacadeMock(),
        );
    }

    /**
     * A single DE/de_DE store, matching every other test in this class' fixtures — those assert on
     * exactly one captured configuration payload, which only holds with exactly one (store, locale) pair
     * in the fan-out.
     */
    protected function createStoreFacadeMock(): SearchRankingStorageToStoreFacadeInterface
    {
        $storeFacadeMock = $this->createMock(SearchRankingStorageToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())->setName('DE')->setAvailableLocaleIsoCodes(['de_DE']),
        ]);

        return $storeFacadeMock;
    }

    /**
     * @param string $name
     * @param float $weight
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
