<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingDataImport\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\DataImporterConfigurationTransfer;
use Generated\Shared\Transfer\DataImporterReportTransfer;
use Spryker\Zed\DataImport\Business\Model\DataImporterInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\SearchRankingDataImportBusinessFactory;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\SearchRankingDataImportFacade;

/**
 * Both methods are a one-hop delegation to a factory-built `DataImporterInterface`, returning exactly
 * what it returns -- the importer's own real CSV-reading behavior is exercised by this package's data
 * import fixtures, not here.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingDataImport
 * @group Business
 * @group SearchRankingDataImportFacadeTest
 * @group Portable
 */
class SearchRankingDataImportFacadeTest extends Unit
{
    public function testImportMetricsDelegatesToTheSearchRankingMetricDataImporter(): void
    {
        // Arrange
        $configurationTransfer = new DataImporterConfigurationTransfer();
        $reportTransfer = new DataImporterReportTransfer();

        $dataImporterMock = $this->createMock(DataImporterInterface::class);
        $dataImporterMock->method('import')->with($configurationTransfer)->willReturn($reportTransfer);

        $factoryMock = $this->getMockBuilder(SearchRankingDataImportBusinessFactory::class)
            ->onlyMethods(['getSearchRankingMetricDataImporter'])
            ->getMock();
        $factoryMock->method('getSearchRankingMetricDataImporter')->willReturn($dataImporterMock);

        $facade = new SearchRankingDataImportFacade();
        $facade->setFactory($factoryMock);

        // Act & Assert
        $this->assertSame($reportTransfer, $facade->importMetrics($configurationTransfer));
    }

    public function testImportProductMetricsDelegatesToTheSearchRankingProductMetricDataImporter(): void
    {
        // Arrange
        $configurationTransfer = new DataImporterConfigurationTransfer();
        $reportTransfer = new DataImporterReportTransfer();

        $dataImporterMock = $this->createMock(DataImporterInterface::class);
        $dataImporterMock->method('import')->with($configurationTransfer)->willReturn($reportTransfer);

        $factoryMock = $this->getMockBuilder(SearchRankingDataImportBusinessFactory::class)
            ->onlyMethods(['getSearchRankingProductMetricDataImporter'])
            ->getMock();
        $factoryMock->method('getSearchRankingProductMetricDataImporter')->willReturn($dataImporterMock);

        $facade = new SearchRankingDataImportFacade();
        $facade->setFactory($factoryMock);

        // Act & Assert
        $this->assertSame($reportTransfer, $facade->importProductMetrics($configurationTransfer));
    }
}
