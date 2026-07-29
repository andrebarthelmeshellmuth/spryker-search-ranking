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
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult;
use SprykerCommunity\Client\SearchRanking\SearchRankingClient;
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
    public function testLeavesTheDebugDataUntouchedWhenTheSectionBuilderReturnsNullAndEntropyDidNotRun(): void
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
            ->with($configurationTransfer, ['top_seller' => 0.51], 6.9244, null)
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
            ->with($configurationTransfer, [], null, null)
            ->willReturn(['title' => 'Business signals']);

        $plugin = $this->createPlugin($storageClientMock, $scoreSectionBuilderMock);

        // Act
        $plugin->expandProductDebugData([], []);
    }

    /**
     * The whole point of this wiring: when entropy weighting ran for this query, the SAME result the
     * query-expander plugin remembered on the Client is what reaches `build()` — not re-derived, not a
     * stale independent config lookup — and a second "Entropy weighting" section gets appended alongside
     * the business-signal one.
     *
     * @return void
     */
    public function testPassesTheRememberedEntropyWeightingResultToTheBuilderAndAppendsAnEntropySection(): void
    {
        // Arrange
        $configurationTransfer = new SearchRankingConfigurationStorageTransfer();
        $entropyWeightingResult = new EntropyWeightingResult(0.75, 0.9, 0.1, 0.15, 10);
        $businessSection = ['title' => 'Business signals'];
        $entropySection = ['title' => 'Entropy weighting'];

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $scoreSectionBuilderMock = $this->createMock(ScoreSectionBuilderInterface::class);
        $scoreSectionBuilderMock->expects($this->once())
            ->method('build')
            ->with($configurationTransfer, [], null, $entropyWeightingResult)
            ->willReturn($businessSection);
        $scoreSectionBuilderMock->expects($this->once())
            ->method('buildEntropySection')
            ->with($entropyWeightingResult)
            ->willReturn($entropySection);

        $plugin = $this->createPlugin($storageClientMock, $scoreSectionBuilderMock, $entropyWeightingResult);

        // Act
        $result = $plugin->expandProductDebugData([], []);

        // Assert
        $this->assertSame([$businessSection, $entropySection], $result[SearchDebugConfig::KEY_SCORE_SECTIONS]);
    }

    /**
     * A shop with no metric weights configured at all (so `build()` returns null — nothing to explain
     * about business signals) can still have entropy weighting enabled: the entropy section must appear
     * on its own, independent of whether the business-signal section exists.
     *
     * @return void
     */
    public function testStillAppendsTheEntropySectionWhenTheBusinessSectionIsNull(): void
    {
        // Arrange
        $configurationTransfer = new SearchRankingConfigurationStorageTransfer();
        $entropyWeightingResult = new EntropyWeightingResult(0.75, 0.9, 0.1, 0.15, 10);
        $entropySection = ['title' => 'Entropy weighting'];

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $scoreSectionBuilderMock = $this->createMock(ScoreSectionBuilderInterface::class);
        $scoreSectionBuilderMock->method('build')->willReturn(null);
        $scoreSectionBuilderMock->expects($this->once())
            ->method('buildEntropySection')
            ->with($entropyWeightingResult)
            ->willReturn($entropySection);

        $plugin = $this->createPlugin($storageClientMock, $scoreSectionBuilderMock, $entropyWeightingResult);

        // Act
        $result = $plugin->expandProductDebugData([], []);

        // Assert
        $this->assertSame([$entropySection], $result[SearchDebugConfig::KEY_SCORE_SECTIONS]);
    }

    /**
     * @param \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface $storageClient
     * @param \SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilderInterface $scoreSectionBuilder
     * @param \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult|null $entropyWeightingResult
     *
     * @return \SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin
     */
    protected function createPlugin(
        SearchRankingToSearchRankingStorageClientInterface $storageClient,
        ScoreSectionBuilderInterface $scoreSectionBuilder,
        ?EntropyWeightingResult $entropyWeightingResult = null,
    ): SearchRankingProductDebugDataExpanderPlugin {
        $factoryMock = $this->createMock(SearchRankingFactory::class);
        $factoryMock->method('getSearchRankingStorageClient')->willReturn($storageClient);
        $factoryMock->method('createScoreSectionBuilder')->willReturn($scoreSectionBuilder);

        $clientMock = $this->createMock(SearchRankingClient::class);
        $clientMock->method('getLastEntropyWeightingResult')->willReturn($entropyWeightingResult);

        $plugin = new SearchRankingProductDebugDataExpanderPlugin();
        $plugin->setFactory($factoryMock);
        $plugin->setClient($clientMock);

        return $plugin;
    }
}
