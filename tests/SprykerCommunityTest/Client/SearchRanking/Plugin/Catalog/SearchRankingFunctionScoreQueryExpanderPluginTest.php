<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Plugin\Catalog;

use Codeception\Test\Unit;
use Elastica\Query;
use Elastica\Query\MatchAll;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult;
use SprykerCommunity\Client\SearchRanking\SearchRankingClient;
use SprykerCommunity\Client\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Client\SearchRanking\SearchRankingFactory;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Plugin
 * @group Catalog
 * @group SearchRankingFunctionScoreQueryExpanderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRanking\SearchRankingClientTester $tester
 */
class SearchRankingFunctionScoreQueryExpanderPluginTest extends Unit
{
    /**
     * A stale result from an earlier query in the same request must never survive into a request that
     * has no search string at all (e.g. a category/browse page) — see
     * SearchRankingClientInterface::rememberLastEntropyWeightingResult()'s docblock.
     *
     * @return void
     */
    public function testResetsAPreviouslyRememberedEntropyResultWhenThereIsNoSearchString(): void
    {
        // Arrange
        $client = new SearchRankingClient();
        $client->rememberLastEntropyWeightingResult(new EntropyWeightingResult(0.75, 0.9, 0.1, 0.15, 10));

        $plugin = $this->createPlugin($client);
        $searchQueryMock = $this->createMock(QueryInterface::class);

        // Act
        $result = $plugin->expandQuery($searchQueryMock, []);

        // Assert
        $this->assertSame($searchQueryMock, $result);
        $this->assertNull($client->getLastEntropyWeightingResult());
    }

    /**
     * @return void
     */
    public function testDoesNotRememberAnEntropyResultWhenEntropyWeightingIsDisabled(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.75);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $entropyCalculatorMock = $this->createMock(EntropyWeightCalculatorInterface::class);
        $entropyCalculatorMock->expects($this->never())->method('calculateWeightingResult');

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with($this->isInstanceOf(MatchAll::class), $configurationTransfer)
            ->willReturn(null);

        $client = new SearchRankingClient();

        $plugin = $this->createPlugin(
            $client,
            $storageClientMock,
            $functionScoreBuilderMock,
            $entropyCalculatorMock,
            false,
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);

        // Assert
        $this->assertNull($client->getLastEntropyWeightingResult());
    }

    /**
     * The core of this wiring: when entropy weighting is enabled, the calculated result is (a) remembered
     * on the Client, for the search-debug overlay to pick up later, AND (b) its `relevanceWeight` is what
     * actually reaches `FunctionScoreBuilder` — the SAME value in both places.
     *
     * @return void
     */
    public function testRemembersTheEntropyWeightingResultAndUsesItsWeightForTheFunctionScore(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.75);
        $entropyWeightingResult = new EntropyWeightingResult(0.75, 0.9, 0.1, 0.15, 10);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $entropyCalculatorMock = $this->createMock(EntropyWeightCalculatorInterface::class);
        $entropyCalculatorMock->expects($this->once())
            ->method('calculateWeightingResult')
            ->with($this->isInstanceOf(MatchAll::class), $configurationTransfer)
            ->willReturn($entropyWeightingResult);

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with(
                $this->isInstanceOf(MatchAll::class),
                $this->callback(
                    static fn (SearchRankingConfigurationStorageTransfer $transfer): bool => $transfer->getRelevanceWeight() === 0.9,
                ),
            )
            ->willReturn(null);

        $client = new SearchRankingClient();

        $plugin = $this->createPlugin(
            $client,
            $storageClientMock,
            $functionScoreBuilderMock,
            $entropyCalculatorMock,
            true,
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);

        // Assert
        $this->assertSame($entropyWeightingResult, $client->getLastEntropyWeightingResult());

        // The original configuration transfer passed into findRankingConfiguration() must stay untouched
        // (a clone carries the adjusted weight, per applyEntropyWeighting()'s docblock).
        $this->assertSame(0.75, $configurationTransfer->getRelevanceWeight());
    }

    /**
     * @param \SprykerCommunity\Client\SearchRanking\SearchRankingClient $client
     * @param \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface|null $storageClient
     * @param \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface|null $functionScoreBuilder
     * @param \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightCalculatorInterface|null $entropyCalculator
     * @param bool $isEntropyWeightingEnabled
     *
     * @return \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin
     */
    protected function createPlugin(
        SearchRankingClient $client,
        ?SearchRankingToSearchRankingStorageClientInterface $storageClient = null,
        ?FunctionScoreBuilderInterface $functionScoreBuilder = null,
        ?EntropyWeightCalculatorInterface $entropyCalculator = null,
        bool $isEntropyWeightingEnabled = false,
    ): SearchRankingFunctionScoreQueryExpanderPlugin {
        $configMock = $this->createMock(SearchRankingConfig::class);
        $configMock->method('isEntropyWeightingEnabled')->willReturn($isEntropyWeightingEnabled);

        $factoryMock = $this->createMock(SearchRankingFactory::class);
        $factoryMock->method('getConfig')->willReturn($configMock);

        if ($storageClient !== null) {
            $factoryMock->method('getSearchRankingStorageClient')->willReturn($storageClient);
        }

        if ($functionScoreBuilder !== null) {
            $factoryMock->method('createFunctionScoreBuilder')->willReturn($functionScoreBuilder);
        }

        if ($entropyCalculator !== null) {
            $factoryMock->method('createEntropyWeightCalculator')->willReturn($entropyCalculator);
        }

        $plugin = new SearchRankingFunctionScoreQueryExpanderPlugin();
        $plugin->setFactory($factoryMock);
        $plugin->setClient($client);

        return $plugin;
    }
}
