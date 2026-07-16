<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\PageData;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ProductPageLoadTransfer;
use Generated\Shared\Transfer\ProductPayloadTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoader;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group PageData
 * @group ScoresPageDataLoaderTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class ScoresPageDataLoaderTest extends Unit
{
    /**
     * @return void
     */
    public function testSetsScoresPerPayloadAndEmptyMapForProductsWithoutScores(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getNormalizedScoresGroupedByIdProductAbstract')
            ->with([101, 102])
            ->willReturn([
                101 => ['top_seller' => 0.25, 'random' => 0.9],
            ]);

        $productPageLoadTransfer = (new ProductPageLoadTransfer())
            ->setProductAbstractIds([101, 102])
            ->setPayloadTransfers([
                (new ProductPayloadTransfer())->setIdProductAbstract(101),
                (new ProductPayloadTransfer())->setIdProductAbstract(102),
            ]);

        // Act
        $resultTransfer = (new ScoresPageDataLoader($repositoryMock))
            ->expandProductPageLoadTransfer($productPageLoadTransfer);

        // Assert
        $payloadTransfers = array_values($resultTransfer->getPayloadTransfers());
        $this->assertSame(['top_seller' => 0.25, 'random' => 0.9], $payloadTransfers[0]->getSearchRankingScores());
        $this->assertSame([], $payloadTransfers[1]->getSearchRankingScores());
    }

    /**
     * @return void
     */
    public function testSkipsRepositoryCallWhenNoProductAbstractIdsAreProvided(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->expects($this->never())->method('getNormalizedScoresGroupedByIdProductAbstract');

        $productPageLoadTransfer = (new ProductPageLoadTransfer())->setProductAbstractIds([]);

        // Act
        (new ScoresPageDataLoader($repositoryMock))->expandProductPageLoadTransfer($productPageLoadTransfer);
    }
}
