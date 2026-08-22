<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingStorage\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\FilterTransfer;
use Generated\Shared\Transfer\SynchronizationDataTransfer;
use SprykerCommunity\Zed\SearchRankingStorage\Business\SearchRankingStorageBusinessFactory;
use SprykerCommunity\Zed\SearchRankingStorage\Business\SearchRankingStorageFacade;
use SprykerCommunity\Zed\SearchRankingStorage\Business\Writer\RankingConfigurationStorageWriterInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageRepository;

/**
 * `publishRankingConfiguration()` delegates to the factory-built writer -- its own real publish logic is
 * covered directly by `RankingConfigurationStorageWriterTest`. `getSearchRankingConfigurationSynchronizationDataTransfers()`
 * delegates straight to the repository. This test's only job is the one hop above each.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingStorage
 * @group Business
 * @group SearchRankingStorageFacadeTest
 * @group Portable
 */
class SearchRankingStorageFacadeTest extends Unit
{
    public function testPublishRankingConfigurationDelegatesToTheRankingConfigurationStorageWriter(): void
    {
        // Arrange
        $writerMock = $this->createMock(RankingConfigurationStorageWriterInterface::class);
        $writerMock->expects($this->once())->method('publishRankingConfiguration');

        $factoryMock = $this->getMockBuilder(SearchRankingStorageBusinessFactory::class)
            ->onlyMethods(['createRankingConfigurationStorageWriter'])
            ->getMock();
        $factoryMock->method('createRankingConfigurationStorageWriter')->willReturn($writerMock);

        $facade = new SearchRankingStorageFacade();
        $facade->setFactory($factoryMock);

        // Act
        $facade->publishRankingConfiguration();
    }

    public function testGetSearchRankingConfigurationSynchronizationDataTransfersDelegatesToTheRepository(): void
    {
        // Arrange
        $filterTransfer = new FilterTransfer();
        $synchronizationDataTransfers = [new SynchronizationDataTransfer()];

        $repositoryMock = $this->getMockBuilder(SearchRankingStorageRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->method('getSearchRankingConfigurationSynchronizationDataTransfers')
            ->with($filterTransfer, [1, 2])
            ->willReturn($synchronizationDataTransfers);

        $facade = new SearchRankingStorageFacade();
        $facade->setRepository($repositoryMock);

        // Act & Assert
        $this->assertSame(
            $synchronizationDataTransfers,
            $facade->getSearchRankingConfigurationSynchronizationDataTransfers($filterTransfer, [1, 2]),
        );
    }
}
