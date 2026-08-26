<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Plugin\Catalog;

use Codeception\Test\Unit;
use Elastica\Query;
use Elastica\Query\FunctionScore;
use Elastica\Query\MatchAll;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;
use Generated\Shared\Transfer\StoreTransfer;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToLocaleClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface;
use SprykerCommunity\Client\SearchRanking\Intent\MsearchProbeRegistrarPluginInterface;
use SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface;
use SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface;
use SprykerCommunity\Client\SearchRanking\Search\NavigationalRelevanceWeightShiftCalculator;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\SearchRankingClient;
use SprykerCommunity\Client\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Client\SearchRanking\SearchRankingFactory;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingClientInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException;
use SprykerCommunity\Client\SearchRanking\Semantic\SemanticQueryEmbeddingCacheInterface;

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
 * @group NeedsSearch
 */
class SearchRankingFunctionScoreQueryExpanderPluginTest extends Unit
{
    /**
     * A stale result from an earlier query in the same request must never survive into a request that
     * has no search string at all (e.g. a category/browse page) — see
     * SearchRankingClientInterface::rememberLastSpecificityWeightingResult()'s docblock.
     */
    public function testResetsAPreviouslyRememberedSpecificityResultWhenThereIsNoSearchString(): void
    {
        // Arrange
        $client = new SearchRankingClient();
        $client->rememberLastSpecificityWeightingResult((new SearchRankingSpecificityWeightingResultTransfer())->setConfiguredRelevanceWeight(0.75)->setRelevanceWeight(0.9)->setNormalizedSpecificity(0.1)->setShift(0.15)->setQueryTermCount(10));

        $plugin = $this->createPlugin($client);
        $searchQueryMock = $this->createMock(QueryInterface::class);

        // Act
        $result = $plugin->expandQuery($searchQueryMock, []);

        // Assert
        $this->assertSame($searchQueryMock, $result);
        $this->assertNull($client->getLastSpecificityWeightingResult());
    }

    public function testDoesNotRememberASpecificityResultWhenSpecificityWeightingIsDisabled(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.75);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $specificityCalculatorMock = $this->createMock(SpecificityWeightCalculatorInterface::class);
        $specificityCalculatorMock->expects($this->never())->method('registerProbes');
        $specificityCalculatorMock->expects($this->never())->method('consumeProbes');

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with($this->isInstanceOf(MatchAll::class), $configurationTransfer, null)
            ->willReturn(null);

        $client = new SearchRankingClient();

        $plugin = $this->createPlugin(
            $client,
            $storageClientMock,
            $functionScoreBuilderMock,
            $specificityCalculatorMock,
            false,
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);

        // Assert
        $this->assertNull($client->getLastSpecificityWeightingResult());
    }

    /**
     * The core of this wiring: when specificity weighting is enabled, the calculated result is (a)
     * remembered on the Client, for the search-debug overlay to pick up later, AND (b) its
     * `relevanceWeight` is what actually reaches `FunctionScoreBuilder` — the SAME value in both places.
     */
    public function testRemembersTheSpecificityWeightingResultAndUsesItsWeightForTheFunctionScore(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.75);
        $specificityWeightingResult = (new SearchRankingSpecificityWeightingResultTransfer())->setConfiguredRelevanceWeight(0.75)->setRelevanceWeight(0.9)->setNormalizedSpecificity(0.1)->setShift(0.15)->setQueryTermCount(10);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $specificityCalculatorMock = $this->createMock(SpecificityWeightCalculatorInterface::class);
        $specificityCalculatorMock->expects($this->once())->method('registerProbes');
        $specificityCalculatorMock->expects($this->once())
            ->method('consumeProbes')
            ->with($this->anything(), $this->anything(), 'gadget', $configurationTransfer)
            ->willReturn($specificityWeightingResult);

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with(
                $this->isInstanceOf(MatchAll::class),
                $this->callback(
                    static fn (SearchRankingConfigurationStorageTransfer $transfer): bool => $transfer->getRelevanceWeight() === 0.9,
                ),
                null,
            )
            ->willReturn(null);

        $client = new SearchRankingClient();

        $plugin = $this->createPlugin(
            $client,
            $storageClientMock,
            $functionScoreBuilderMock,
            $specificityCalculatorMock,
            true,
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);

        // Assert
        $this->assertSame($specificityWeightingResult, $client->getLastSpecificityWeightingResult());

        // The original configuration transfer passed into findRankingConfiguration() must stay untouched
        // (a clone carries the adjusted weight, per applySpecificityWeighting()'s docblock).
        $this->assertSame(0.75, $configurationTransfer->getRelevanceWeight());
    }

    /**
     * The finished wiring for {@see MsearchProbeRegistrarPluginInterface}: a project-registered plugin
     * must actually be resolved via {@see SearchRankingFactory::getMsearchProbeRegistrarPlugins()} and
     * invoked during the register phase, with the SAME request-scoped fields a
     * {@see QueryAnalyzerInterface} reads.
     */
    public function testInvokesARegisteredMsearchProbeRegistrarPluginDuringTheRegisterPhase(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.75);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->method('build')->willReturn(new FunctionScore());

        $receivedQueryContextTransfers = [];
        $registrarPluginMock = $this->createMock(MsearchProbeRegistrarPluginInterface::class);
        $registrarPluginMock->expects($this->once())
            ->method('registerProbes')
            ->with(
                $this->isInstanceOf(MsearchProbeBatcherInterface::class),
                $this->callback(function (SearchRankingQueryContextTransfer $transfer) use (&$receivedQueryContextTransfers): bool {
                    $receivedQueryContextTransfers[] = $transfer;

                    return true;
                }),
            );

        $client = new SearchRankingClient();

        $plugin = $this->createPlugin(
            $client,
            $storageClientMock,
            $functionScoreBuilderMock,
            null,
            false,
            null,
            null,
            null,
            [$registrarPluginMock],
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);

        // Assert — the plugin was actually invoked (the mock expectation above), with the real request's
        // search string/store/locale reaching it.
        $this->assertCount(1, $receivedQueryContextTransfers);
        $this->assertSame('gadget', $receivedQueryContextTransfers[0]->getSearchString());
        $this->assertSame('DE', $receivedQueryContextTransfers[0]->getStoreName());
        $this->assertSame('de_DE', $receivedQueryContextTransfers[0]->getLocaleName());
    }

    /**
     * Elastica's own Query::setSource() legally accepts `false` (explicitly disable _source) alongside an
     * array whitelist. A naive `(array)$query->getParam('_source')` would silently turn that `false` into
     * `[]`, then this method would populate it with just 'scores' -- re-enabling a source the caller
     * explicitly turned off. Must leave a boolean `_source` param untouched instead.
     */
    public function testLeavesABooleanSourceParamUntouchedInsteadOfCorruptingItIntoAnArray(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.75);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->method('build')->willReturn(new FunctionScore());

        $client = new SearchRankingClient();

        $plugin = $this->createPlugin($client, $storageClientMock, $functionScoreBuilderMock);

        $query = new Query(new MatchAll());
        $query->setSource(false);

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn($query);

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);

        // Assert
        $this->assertFalse($query->getParam('_source'));
    }

    /**
     * On any embedding failure, the plugin must NOT propagate the exception and must produce the same
     * query it would have without hybrid search at all — the mandatory graceful-degradation path.
     */
    public function testDegradesToLexicalOnlyWhenEmbeddingServiceThrows(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRelevanceWeight(0.75)
            ->setAlpha(0.4);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $embeddingCacheMock = $this->createMock(SemanticQueryEmbeddingCacheInterface::class);
        $embeddingCacheMock->method('get')->willReturn(null);
        $embeddingCacheMock->expects($this->never())->method('set');

        $embeddingClientMock = $this->createMock(EmbeddingClientInterface::class);
        $embeddingClientMock->method('embed')->willThrowException(new EmbeddingUnavailableException('down'));

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with($this->isInstanceOf(MatchAll::class), $configurationTransfer, null)
            ->willReturn(null);

        $plugin = $this->createPlugin(
            new SearchRankingClient(),
            $storageClientMock,
            $functionScoreBuilderMock,
            null,
            false,
            $embeddingClientMock,
            $embeddingCacheMock,
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $result = $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);

        // Assert — no exception propagated, original query object returned untouched by the crash.
        $this->assertSame($searchQueryMock, $result);
    }

    /**
     * `alpha == 1.0` (unset counts as the same default) must never even ask the embedding cache/client —
     * paying for an embedding that could never be blended in would be pure waste.
     */
    public function testNeverResolvesAQueryVectorWhenAlphaIsAtItsDefault(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.75);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $embeddingCacheMock = $this->createMock(SemanticQueryEmbeddingCacheInterface::class);
        $embeddingCacheMock->expects($this->never())->method('get');

        $embeddingClientMock = $this->createMock(EmbeddingClientInterface::class);
        $embeddingClientMock->expects($this->never())->method('embed');

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->method('build')->willReturn(null);

        $plugin = $this->createPlugin(
            new SearchRankingClient(),
            $storageClientMock,
            $functionScoreBuilderMock,
            null,
            false,
            $embeddingClientMock,
            $embeddingCacheMock,
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);
    }

    /**
     * A resolved query vector (cache hit, no embedding-service round trip needed) reaches
     * `FunctionScoreBuilder::build()` as its third argument.
     */
    public function testPassesACachedQueryVectorIntoTheFunctionScoreBuilder(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRelevanceWeight(0.75)
            ->setAlpha(0.4);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $embeddingCacheMock = $this->createMock(SemanticQueryEmbeddingCacheInterface::class);
        $embeddingCacheMock->method('get')->willReturn([0.1, 0.2, 0.3]);
        $embeddingCacheMock->expects($this->never())->method('set');

        $embeddingClientMock = $this->createMock(EmbeddingClientInterface::class);
        $embeddingClientMock->expects($this->never())->method('embed');

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with($this->isInstanceOf(MatchAll::class), $configurationTransfer, [0.1, 0.2, 0.3])
            ->willReturn(null);

        $plugin = $this->createPlugin(
            new SearchRankingClient(),
            $storageClientMock,
            $functionScoreBuilderMock,
            null,
            false,
            $embeddingClientMock,
            $embeddingCacheMock,
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'gadget']);
    }

    /**
     * With both navigational shift fields left at their real default (unset / 0.0), a detected brand must
     * change NOTHING — the feature is inert unless a project deliberately configures a nonzero shift.
     */
    public function testIsANoOpWhenABrandIsDetectedButNoShiftIsConfigured(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.75);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with(
                $this->isInstanceOf(MatchAll::class),
                $this->callback(
                    static fn (SearchRankingConfigurationStorageTransfer $transfer): bool => $transfer->getRelevanceWeight() === 0.75,
                ),
                null,
            )
            ->willReturn(null);

        $plugin = $this->createPlugin(
            new SearchRankingClient(),
            $storageClientMock,
            $functionScoreBuilderMock,
            null,
            false,
            null,
            null,
            $this->createBrandDetectingAnalyzer('Topstar'),
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'Topstar swivel chair']);
    }

    /**
     * The core of the wiring: a configured, nonzero `brandMatchRelevanceWeightShift` actually reaches
     * `FunctionScoreBuilder` as an adjusted `relevanceWeight` once a brand is detected on the query.
     */
    public function testAppliesTheConfiguredBrandShiftWhenABrandIsDetected(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRelevanceWeight(0.75)
            ->setBrandMatchRelevanceWeightShift(0.1);

        $storageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $storageClientMock->method('findRankingConfiguration')->willReturn($configurationTransfer);

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with(
                $this->isInstanceOf(MatchAll::class),
                $this->callback(
                    static fn (SearchRankingConfigurationStorageTransfer $transfer): bool => $transfer->getRelevanceWeight() === 0.85,
                ),
                null,
            )
            ->willReturn(null);

        $plugin = $this->createPlugin(
            new SearchRankingClient(),
            $storageClientMock,
            $functionScoreBuilderMock,
            null,
            false,
            null,
            null,
            $this->createBrandDetectingAnalyzer('Topstar'),
        );

        $searchQueryMock = $this->createMock(QueryInterface::class);
        $searchQueryMock->method('getSearchQuery')->willReturn(new Query(new MatchAll()));

        // Act
        $plugin->expandQuery($searchQueryMock, ['q' => 'Topstar swivel chair']);

        // Assert — the original configuration transfer must stay untouched, same discipline as
        // applySpecificityWeighting()'s own clone.
        $this->assertSame(0.75, $configurationTransfer->getRelevanceWeight());
    }

    protected function createBrandDetectingAnalyzer(string $detectedBrand): QueryAnalyzerInterface
    {
        $analyzerMock = $this->createMock(QueryAnalyzerInterface::class);
        $analyzerMock->method('analyze')->willReturnCallback(
            static fn (SearchRankingQueryContextTransfer $transfer): SearchRankingQueryContextTransfer => $transfer->setDetectedBrand($detectedBrand),
        );

        return $analyzerMock;
    }

    /**
     * @param \SprykerCommunity\Client\SearchRanking\SearchRankingClient $client
     * @param \SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface|null $storageClient
     * @param \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface|null $functionScoreBuilder
     * @param \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculatorInterface|null $specificityCalculator
     * @param bool $isSpecificityWeightingEnabled
     * @param \SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingClientInterface|null $embeddingClient
     * @param \SprykerCommunity\Client\SearchRanking\Semantic\SemanticQueryEmbeddingCacheInterface|null $embeddingCache
     * @param \SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface|null $queryAnalyzer
     * @param array<\SprykerCommunity\Client\SearchRanking\Intent\MsearchProbeRegistrarPluginInterface>|null $msearchProbeRegistrarPlugins
     */
    protected function createPlugin(
        SearchRankingClient $client,
        ?SearchRankingToSearchRankingStorageClientInterface $storageClient = null,
        ?FunctionScoreBuilderInterface $functionScoreBuilder = null,
        ?SpecificityWeightCalculatorInterface $specificityCalculator = null,
        bool $isSpecificityWeightingEnabled = false,
        ?EmbeddingClientInterface $embeddingClient = null,
        ?SemanticQueryEmbeddingCacheInterface $embeddingCache = null,
        ?QueryAnalyzerInterface $queryAnalyzer = null,
        ?array $msearchProbeRegistrarPlugins = null,
    ): SearchRankingFunctionScoreQueryExpanderPlugin {
        $configMock = $this->createMock(SearchRankingConfig::class);
        $configMock->method('isSpecificityWeightingEnabled')->willReturn($isSpecificityWeightingEnabled);
        $configMock->method('getEmbeddingModelId')->willReturn('sentence-transformers/all-MiniLM-L6-v2');

        $storeClientMock = $this->createMock(SearchRankingToStoreClientInterface::class);
        $storeClientMock->method('getCurrentStore')->willReturn((new StoreTransfer())->setName('DE'));

        $localeClientMock = $this->createMock(SearchRankingToLocaleClientInterface::class);
        $localeClientMock->method('getCurrentLocale')->willReturn('de_DE');

        $factoryMock = $this->createMock(SearchRankingFactory::class);
        $factoryMock->method('getConfig')->willReturn($configMock);
        $factoryMock->method('getStoreClient')->willReturn($storeClientMock);
        $factoryMock->method('getLocaleClient')->willReturn($localeClientMock);

        if ($storageClient !== null) {
            $factoryMock->method('getSearchRankingStorageClient')->willReturn($storageClient);
        }

        if ($functionScoreBuilder !== null) {
            $factoryMock->method('createFunctionScoreBuilder')->willReturn($functionScoreBuilder);
        }

        if ($specificityCalculator !== null) {
            $factoryMock->method('createSpecificityWeightCalculator')->willReturn($specificityCalculator);
        }

        // Real instance, not a mock: it's a pure, side-effect-free calculator, and with the configuration
        // transfers every existing test builds (no brand/category shift configured) it's a guaranteed
        // no-op — see NavigationalRelevanceWeightShiftCalculatorTest for its own dedicated coverage.
        $factoryMock->method('createNavigationalRelevanceWeightShiftCalculator')->willReturn(new NavigationalRelevanceWeightShiftCalculator());

        if ($embeddingClient !== null) {
            $factoryMock->method('createEmbeddingClient')->willReturn($embeddingClient);
        }

        if ($embeddingCache !== null) {
            $factoryMock->method('createSemanticQueryEmbeddingCache')->willReturn($embeddingCache);
        }

        $factoryMock->method('getQueryAnalyzers')->willReturn($queryAnalyzer !== null ? [$queryAnalyzer] : []);
        $factoryMock->method('getMsearchProbeRegistrarPlugins')->willReturn($msearchProbeRegistrarPlugins ?? []);

        $plugin = new SearchRankingFunctionScoreQueryExpanderPlugin();
        $plugin->setFactory($factoryMock);
        $plugin->setClient($client);

        return $plugin;
    }
}
