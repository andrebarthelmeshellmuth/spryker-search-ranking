<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Publisher;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisher;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Publisher
 * @group ProductAbstractScorePublisherTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class ProductAbstractScorePublisherTest extends Unit
{
    public function testTriggersChunkedPublishEventsForAllScoredProducts(): void
    {
        // Arrange
        $productAbstractIds = range(1, 1200);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getProductAbstractIdsWithActiveMetricValues')->willReturn($productAbstractIds);

        $triggeredEventNames = [];
        $triggeredIdChunks = [];
        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->method('triggerBulk')
            ->willReturnCallback(function (string $eventName, array $eventEntityTransfers) use (&$triggeredEventNames, &$triggeredIdChunks): void {
                $triggeredEventNames[] = $eventName;
                $triggeredIdChunks[] = array_map(
                    fn ($eventEntityTransfer) => $eventEntityTransfer->getId(),
                    $eventEntityTransfers,
                );
            });

        $publisher = new ProductAbstractScorePublisher($repositoryMock, $eventFacadeMock, new SearchRankingConfig());

        // Act
        $publishedProductCount = $publisher->publishScoredProductAbstracts();

        // Assert: 1200 ids in chunks of 500 -> 3 bulk triggers of the product abstract publish event
        $this->assertSame(1200, $publishedProductCount);
        $this->assertSame(['Product.product_abstract.publish', 'Product.product_abstract.publish', 'Product.product_abstract.publish'], $triggeredEventNames);
        $this->assertSame([500, 500, 200], array_map(count(...), $triggeredIdChunks));
        $this->assertSame($productAbstractIds, array_merge(...$triggeredIdChunks));
    }

    public function testTriggersNothingWhenNoProductHasScores(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getProductAbstractIdsWithActiveMetricValues')->willReturn([]);

        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->expects($this->never())->method('triggerBulk');

        $publisher = new ProductAbstractScorePublisher($repositoryMock, $eventFacadeMock, new SearchRankingConfig());

        // Act
        $publishedProductCount = $publisher->publishScoredProductAbstracts();

        // Assert
        $this->assertSame(0, $publishedProductCount);
    }
}
