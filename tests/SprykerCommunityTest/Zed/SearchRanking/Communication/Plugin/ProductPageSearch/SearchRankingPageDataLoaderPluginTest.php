<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Plugin\ProductPageSearch;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ProductPageLoadTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade;
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingPageDataLoaderPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Plugin
 * @group ProductPageSearch
 * @group SearchRankingPageDataLoaderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class SearchRankingPageDataLoaderPluginTest extends Unit
{
    /**
     * @return void
     */
    public function testDelegatesToTheFacadeAndReturnsItsResult(): void
    {
        // Arrange
        $productPageLoadTransfer = (new ProductPageLoadTransfer())->setProductAbstractIds([101, 102]);
        $expandedProductPageLoadTransfer = (clone $productPageLoadTransfer)->setProductAbstractIds([101, 102]);

        $facadeMock = $this->createMock(SearchRankingFacade::class);
        $facadeMock->expects($this->once())
            ->method('expandProductPageLoadTransferWithScores')
            ->with($productPageLoadTransfer)
            ->willReturn($expandedProductPageLoadTransfer);

        $plugin = new SearchRankingPageDataLoaderPlugin();
        $plugin->setFacade($facadeMock);

        // Act
        $result = $plugin->expandProductPageDataTransfer($productPageLoadTransfer);

        // Assert
        $this->assertSame($expandedProductPageLoadTransfer, $result);
    }
}
