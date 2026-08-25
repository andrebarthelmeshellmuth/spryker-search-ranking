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
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingEmbeddingPageDataLoaderPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Plugin
 * @group ProductPageSearch
 * @group SearchRankingEmbeddingPageDataLoaderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group Portable
 */
class SearchRankingEmbeddingPageDataLoaderPluginTest extends Unit
{
    public function testDelegatesToTheFacadeAndReturnsItsResult(): void
    {
        // Arrange
        $productPageLoadTransfer = (new ProductPageLoadTransfer())->setProductAbstractIds([101, 102]);
        $expandedProductPageLoadTransfer = (clone $productPageLoadTransfer)->setProductAbstractIds([101, 102]);

        $facadeMock = $this->createMock(SearchRankingFacade::class);
        $facadeMock->expects($this->once())
            ->method('expandProductPageLoadTransferWithEmbeddings')
            ->with($productPageLoadTransfer)
            ->willReturn($expandedProductPageLoadTransfer);

        $plugin = new SearchRankingEmbeddingPageDataLoaderPlugin();
        $plugin->setFacade($facadeMock);

        // Act
        $result = $plugin->expandProductPageDataTransfer($productPageLoadTransfer);

        // Assert
        $this->assertSame($expandedProductPageLoadTransfer, $result);
    }
}
