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
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingEntityLookupSyncPlugin;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Plugin
 * @group ProductPageSearch
 * @group SearchRankingEntityLookupSyncPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingCommunicationTester $tester
 * @group Portable
 */
class SearchRankingEntityLookupSyncPluginTest extends Unit
{
    public function testCallsTheFacadeWithTheBatchsProductAbstractIdsWhenEventSyncIsEnabled(): void
    {
        // Arrange
        $productPageLoadTransfer = (new ProductPageLoadTransfer())->setProductAbstractIds([1, 2, 3]);

        $facadeMock = $this->createMock(SearchRankingFacade::class);
        $facadeMock->expects($this->once())
            ->method('syncEntityLookupForProductAbstracts')
            ->with([1, 2, 3]);

        $plugin = $this->createPlugin($facadeMock, isEventSyncEnabled: true);

        // Act
        $result = $plugin->expandProductPageDataTransfer($productPageLoadTransfer);

        // Assert
        $this->assertSame($productPageLoadTransfer, $result);
    }

    public function testNoOpsWhenEventSyncIsNotEnabled(): void
    {
        // Arrange
        $productPageLoadTransfer = (new ProductPageLoadTransfer())->setProductAbstractIds([1, 2, 3]);

        $facadeMock = $this->createMock(SearchRankingFacade::class);
        $facadeMock->expects($this->never())->method('syncEntityLookupForProductAbstracts');

        $plugin = $this->createPlugin($facadeMock, isEventSyncEnabled: false);

        // Act
        $result = $plugin->expandProductPageDataTransfer($productPageLoadTransfer);

        // Assert
        $this->assertSame($productPageLoadTransfer, $result);
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade|\PHPUnit\Framework\MockObject\MockObject $facade
     * @param bool $isEventSyncEnabled
     */
    protected function createPlugin($facade, bool $isEventSyncEnabled): SearchRankingEntityLookupSyncPlugin
    {
        $configMock = $this->createMock(SearchRankingConfig::class);
        $configMock->method('isEntityLookupEventSyncEnabled')->willReturn($isEventSyncEnabled);

        $plugin = new SearchRankingEntityLookupSyncPlugin();
        $plugin->setFacade($facade);
        $plugin->setConfig($configMock);

        return $plugin;
    }
}
