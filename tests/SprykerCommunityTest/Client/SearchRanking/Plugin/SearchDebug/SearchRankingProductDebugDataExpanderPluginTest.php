<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Plugin\SearchDebug;

use Codeception\Test\Unit;
use Generated\Shared\Search\PageIndexMap;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Client\SearchDebug\Explanation\ExplanationParser;
use SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin;
use SprykerCommunity\Client\SearchRanking\SearchRankingFactory;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Plugin
 * @group SearchDebug
 * @group SearchRankingProductDebugDataExpanderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRanking\SearchRankingClientTester $tester
 */
class SearchRankingProductDebugDataExpanderPluginTest extends Unit
{
    /**
     * @return void
     */
    public function testLeavesTheDebugDataUntouchedWhenNoRankingConfigurationIsSynchronized(): void
    {
        // Arrange
        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn(null);

        $scoreSectionBuilderMock = $this->createMock(ScoreSectionBuilderInterface::class);
        $scoreSectionBuilderMock->expects($this->never())->method('build');

        $plugin = $this->createPlugin($storageClientMock, $scoreSectionBuilderMock);
        $productDebugData = ['existing' => 'value'];

        // Act
        $result = $plugin->expandProductDebugData($productDebugData, []);

        // Assert
        $this->assertSame($productDebugData, $result);
    }

    /**
     * @return void
     */
    public function testLeavesTheDebugDataUntouchedWhenTheSectionBuilderReturnsNull(): void
    {
        // Arrange
        $configurationTransfer = new SearchRankingConfigurationStorageTransfer();

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $scoreSectionBuilderMock = $this->createMock(ScoreSectionBuilderInterface::class);
        $scoreSectionBuilderMock->method('build')->willReturn(null);

        $plugin = $this->createPlugin($storageClientMock, $scoreSectionBuilderMock);
        $productDebugData = ['existing' => 'value'];

        // Act
        $result = $plugin->expandProductDebugData($productDebugData, []);

        // Assert
        $this->assertSame($productDebugData, $result);
    }

    /**
     * @return void
     */
    public function testAppendsTheBuiltSectionUnderTheScoreSectionsKeyUsingTheDocumentScoresAndQueryScore(): void
    {
        // Arrange
        $configurationTransfer = new SearchRankingConfigurationStorageTransfer();
        $builtSection = ['title' => 'Business signals'];

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $documentSource = [PageIndexMap::SCORES => ['top_seller' => 0.51]];
        $productDebugData = [ExplanationParser::KEY_QUERY_SCORE => 6.9244];

        $scoreSectionBuilderMock = $this->createMock(ScoreSectionBuilderInterface::class);
        $scoreSectionBuilderMock->expects($this->once())
            ->method('build')
            ->with($configurationTransfer, ['top_seller' => 0.51], 6.9244)
            ->willReturn($builtSection);

        $plugin = $this->createPlugin($storageClientMock, $scoreSectionBuilderMock);

        // Act
        $result = $plugin->expandProductDebugData($productDebugData, $documentSource);

        // Assert
        $this->assertSame([$builtSection], $result[SearchDebugConfig::KEY_SCORE_SECTIONS]);
    }

    /**
     * A document without a `scores` field and debug data without a query score must still call the
     * builder — with an empty scores array and a `null` query score, not skip it.
     *
     * @return void
     */
    public function testPassesAnEmptyScoresArrayAndNullQueryScoreWhenBothAreMissing(): void
    {
        // Arrange
        $configurationTransfer = new SearchRankingConfigurationStorageTransfer();

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $scoreSectionBuilderMock = $this->createMock(ScoreSectionBuilderInterface::class);
        $scoreSectionBuilderMock->expects($this->once())
            ->method('build')
            ->with($configurationTransfer, [], null)
            ->willReturn(['title' => 'Business signals']);

        $plugin = $this->createPlugin($storageClientMock, $scoreSectionBuilderMock);

        // Act
        $plugin->expandProductDebugData([], []);
    }

    /**
     * @param \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface $storageClient
     * @param \SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilderInterface $scoreSectionBuilder
     *
     * @return \SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin
     */
    protected function createPlugin(
        SearchRankingToSearchRankingStorageClientInterface $storageClient,
        ScoreSectionBuilderInterface $scoreSectionBuilder,
    ): SearchRankingProductDebugDataExpanderPlugin {
        $factoryMock = $this->createMock(SearchRankingFactory::class);
        $factoryMock->method('getSearchRankingStorageClient')->willReturn($storageClient);
        $factoryMock->method('createScoreSectionBuilder')->willReturn($scoreSectionBuilder);

        $plugin = new SearchRankingProductDebugDataExpanderPlugin();
        $plugin->setFactory($factoryMock);

        return $plugin;
    }
}
