<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Elastica\Client;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilder;
use SprykerCommunity\Client\SearchRanking\Debug\ScoreSectionBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToLocaleClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToPermissionClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface;
use SprykerCommunity\Client\SearchRanking\Intent\BrandAnalyzer;
use SprykerCommunity\Client\SearchRanking\Intent\CachingEntityLookupDecorator;
use SprykerCommunity\Client\SearchRanking\Intent\CategoryAnalyzer;
use SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface;
use SprykerCommunity\Client\SearchRanking\Intent\SkuIdentifierAnalyzer;
use SprykerCommunity\Client\SearchRanking\Intent\SuggestIndexEntityLookup;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\RandomImpact\RandomImpactCalculator;
use SprykerCommunity\Client\SearchRanking\RandomImpact\RandomImpactCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityChecker;
use SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityCheckerInterface;
use SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcher;
use SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface;
use SprykerCommunity\Client\SearchRanking\Search\NavigationalRelevanceWeightShiftCalculator;
use SprykerCommunity\Client\SearchRanking\Search\NavigationalRelevanceWeightShiftCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculator;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcher;
use SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculatorInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingClientInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\SemanticQueryEmbeddingCache;
use SprykerCommunity\Client\SearchRanking\Semantic\SemanticQueryEmbeddingCacheInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\TextEmbeddingsInferenceClient;
use SprykerCommunity\Client\SearchRanking\Strategy\AdaptiveFormulaStrategy;
use SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyInterface;
use SprykerCommunity\Shared\SearchRanking\Intent\QueryWindowExtractor;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingFactory extends AbstractFactory
{
    public function createFunctionScoreBuilder(): FunctionScoreBuilderInterface
    {
        return new FunctionScoreBuilder();
    }

    /**
     * The active {@see RankingStrategyInterface} stack for the current request — this package's own
     * built-in default ({@see AdaptiveFormulaStrategy}, always first) plus whatever a project registered
     * via {@see SearchRankingDependencyProvider::PLUGINS_RANKING_STRATEGY}. Mirrors {@see getQueryAnalyzers()}:
     * a fresh instance every call, built-in default first, project plugins appended.
     *
     * @return array<\SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyInterface>
     */
    public function getRankingStrategies(): array
    {
        return array_merge(
            [
                $this->createAdaptiveFormulaStrategy(),
            ],
            $this->getRankingStrategyPlugins(),
        );
    }

    public function createAdaptiveFormulaStrategy(): RankingStrategyInterface
    {
        return new AdaptiveFormulaStrategy($this->createFunctionScoreBuilder());
    }

    /**
     * @return array<\SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyInterface>
     */
    public function getRankingStrategyPlugins(): array
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::PLUGINS_RANKING_STRATEGY);
    }

    /**
     * Resolves the strategy for this query: the FIRST registered strategy (other than the built-in
     * fallback) whose {@see RankingStrategyInterface::supports()} returns `true`, else
     * {@see AdaptiveFormulaStrategy} — which `supports()` everything and is therefore the guaranteed
     * last-resort fallback regardless of where it sits in the stack. With no project plugins registered,
     * {@see AdaptiveFormulaStrategy} is always the resolved strategy, byte-identical to pre-Phase-3.
     *
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    public function resolveRankingStrategy(SearchRankingQueryContextTransfer $queryContextTransfer): RankingStrategyInterface
    {
        $fallbackRankingStrategy = null;

        foreach ($this->getRankingStrategies() as $rankingStrategy) {
            if ($rankingStrategy->getName() === AdaptiveFormulaStrategy::NAME) {
                $fallbackRankingStrategy = $rankingStrategy;

                continue;
            }

            if ($rankingStrategy->supports($queryContextTransfer)) {
                return $rankingStrategy;
            }
        }

        return $fallbackRankingStrategy ?? $this->createAdaptiveFormulaStrategy();
    }

    public function createRandomImpactCalculator(): RandomImpactCalculatorInterface
    {
        return new RandomImpactCalculator();
    }

    public function createScoreSectionBuilder(): ScoreSectionBuilderInterface
    {
        return new ScoreSectionBuilder();
    }

    public function getSearchRankingStorageClient(): SearchRankingToSearchRankingStorageClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_SEARCH_RANKING_STORAGE);
    }

    public function createEngineCompatibilityChecker(): EngineCompatibilityCheckerInterface
    {
        return new EngineCompatibilityChecker($this->getElasticaClient());
    }

    public function createSpecificityWeightCalculator(): SpecificityWeightCalculatorInterface
    {
        return new SpecificityWeightCalculator(
            $this->createQueryTermFrequencyFetcher(),
            $this->createQuerySpecificityCalculator(),
            $this->getConfig()->getSpecificityProbeFieldSearchAnalyzers(),
        );
    }

    public function getStoreClient(): SearchRankingToStoreClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_STORE);
    }

    public function getLocaleClient(): SearchRankingToLocaleClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_LOCALE);
    }

    public function getPermissionClient(): SearchRankingToPermissionClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_PERMISSION);
    }

    public function createQueryTermFrequencyFetcher(): QueryTermFrequencyFetcherInterface
    {
        return new QueryTermFrequencyFetcher(
            $this->getElasticaClient(),
            $this->createSearchElasticsearchConfig(),
            $this->getStoreClient(),
        );
    }

    public function createQuerySpecificityCalculator(): QuerySpecificityCalculatorInterface
    {
        return new QuerySpecificityCalculator();
    }

    public function createNavigationalRelevanceWeightShiftCalculator(): NavigationalRelevanceWeightShiftCalculatorInterface
    {
        return new NavigationalRelevanceWeightShiftCalculator();
    }

    /**
     * COMPOSITION over the core SearchElasticsearch module, deliberately — same pattern (and the same
     * reasoning) as `SprykerCommunity\Client\SearchDebug\SearchDebugFactory::getElasticaClient()`.
     */
    public function getElasticaClient(): Client
    {
        return $this->createElasticaClientFactory()->createClient(
            $this->createSearchElasticsearchConfig()->getClientConfig(),
        );
    }

    public function createElasticaClientFactory(): ElasticaClientFactory
    {
        return new ElasticaClientFactory();
    }

    public function createSearchElasticsearchConfig(): SearchElasticsearchConfig
    {
        return new SearchElasticsearchConfig();
    }

    public function createEmbeddingClient(): EmbeddingClientInterface
    {
        return new TextEmbeddingsInferenceClient($this->getConfig()->getEmbeddingServiceUrl());
    }

    public function createSemanticQueryEmbeddingCache(): SemanticQueryEmbeddingCacheInterface
    {
        return new SemanticQueryEmbeddingCache($this->getStorageClient());
    }

    public function getStorageClient(): SearchRankingToStorageClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_STORAGE);
    }

    /**
     * The active {@see QueryAnalyzerInterface} stack for the current request — this package's own
     * built-in default (today: just {@see SkuIdentifierAnalyzer}) plus whatever a project registered via
     * {@see SearchRankingDependencyProvider::PLUGINS_QUERY_ANALYZER}. A fresh instance every call
     * (analyzers hold no state of their own between requests; the per-request state lives on the
     * `SearchRankingQueryContextTransfer` they build, not on the analyzers themselves).
     *
     * `$entityLookupOverrides` (keyed by `SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_*`) lets a caller
     * — see {@see createBatchedEntityLookupOverrides()} and
     * {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     * — hand the built-in sku/brand/category analyzers a pre-warmed
     * {@see \SprykerCommunity\Client\SearchRanking\Intent\CachingEntityLookupDecorator} instead of letting
     * them build their own lookup the normal way, so their `exists()` calls read from an already-fetched
     * shared `_msearch` batch rather than firing their own separate request each. Left empty (the default),
     * behavior is BYTE-IDENTICAL to before this override param existed.
     *
     * @param array<string, \SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface> $entityLookupOverrides
     *
     * @return array<\SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface>
     */
    public function getQueryAnalyzers(array $entityLookupOverrides = []): array
    {
        return array_merge(
            [
                $this->createSkuIdentifierAnalyzer($entityLookupOverrides[SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_SKU] ?? null),
                $this->createBrandAnalyzer(
                    $entityLookupOverrides[SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_BRAND] ?? null,
                    $entityLookupOverrides[SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_CATEGORY] ?? null,
                ),
                $this->createCategoryAnalyzer($entityLookupOverrides[SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_CATEGORY] ?? null),
            ],
            $this->getQueryAnalyzerPlugins(),
        );
    }

    public function createSkuIdentifierAnalyzer(?EntityLookupInterface $skuEntityLookupOverride = null): SkuIdentifierAnalyzer
    {
        return new SkuIdentifierAnalyzer($skuEntityLookupOverride ?? $this->createSkuEntityLookup());
    }

    public function createBrandAnalyzer(
        ?EntityLookupInterface $brandEntityLookupOverride = null,
        ?EntityLookupInterface $categoryEntityLookupOverride = null,
    ): BrandAnalyzer {
        return new BrandAnalyzer(
            $brandEntityLookupOverride ?? $this->createBrandEntityLookup(),
            $categoryEntityLookupOverride ?? $this->createCategoryEntityLookup(),
        );
    }

    public function createCategoryAnalyzer(?EntityLookupInterface $categoryEntityLookupOverride = null): CategoryAnalyzer
    {
        return new CategoryAnalyzer($categoryEntityLookupOverride ?? $this->createCategoryEntityLookup());
    }

    public function createMsearchProbeBatcher(): MsearchProbeBatcherInterface
    {
        return new MsearchProbeBatcher($this->getElasticaClient());
    }

    /**
     * Builds the sku/brand/category {@see \SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface}
     * OVERRIDE map for {@see getQueryAnalyzers()} — one {@see \SprykerCommunity\Client\SearchRanking\Intent\CachingEntityLookupDecorator}
     * per entity type, UNCONDITIONALLY (ES is the only entity-lookup backend now — see this package's
     * README/install checker for the one prerequisite), pre-registered with every candidate window/term the
     * built-in analyzers will actually check, onto `$batcher`.
     *
     * Candidate terms mirror EXACTLY what {@see \SprykerCommunity\Client\SearchRanking\Intent\SkuIdentifierAnalyzer}
     * (the whole search string) and {@see \SprykerCommunity\Client\SearchRanking\Intent\BrandAnalyzer}/
     * {@see \SprykerCommunity\Client\SearchRanking\Intent\CategoryAnalyzer} (every
     * {@see \SprykerCommunity\Shared\SearchRanking\Intent\QueryWindowExtractor} window) would look up on
     * their own — registering the SAME set up front is what lets their later `exists()` calls hit the
     * pre-warmed cache instead of falling back to an uncached request per call.
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param string $searchString
     *
     * @return array<string, \SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface>
     */
    public function createBatchedEntityLookupOverrides(MsearchProbeBatcherInterface $batcher, string $searchString): array
    {
        $overrides = [];

        $termsByType = [
            SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_SKU => [$searchString],
            SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_BRAND => QueryWindowExtractor::extractWindows($searchString),
            SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_CATEGORY => QueryWindowExtractor::extractWindows($searchString),
        ];

        foreach ($termsByType as $type => $terms) {
            $lookup = $this->createSuggestIndexEntityLookup($type);
            $decorator = new CachingEntityLookupDecorator($lookup, $batcher, 'entity:' . $type);

            foreach ($terms as $term) {
                $decorator->registerProbe($term);
            }

            $overrides[$type] = $decorator;
        }

        return $overrides;
    }

    public function createSkuEntityLookup(): EntityLookupInterface
    {
        return $this->createSuggestIndexEntityLookup(SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_SKU);
    }

    public function createBrandEntityLookup(): EntityLookupInterface
    {
        return $this->createSuggestIndexEntityLookup(SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_BRAND);
    }

    public function createCategoryEntityLookup(): EntityLookupInterface
    {
        return $this->createSuggestIndexEntityLookup(SharedSearchRankingConfig::ENTITY_LOOKUP_TYPE_CATEGORY);
    }

    /**
     * @param string $type
     */
    public function createSuggestIndexEntityLookup(string $type): SuggestIndexEntityLookup
    {
        return new SuggestIndexEntityLookup(
            $this->getElasticaClient(),
            $this->resolveEntityLookupSuggestIndexName(),
            $type,
        );
    }

    /**
     * Same `{prefix}_{store}_{sourceIdentifier}` index-name scheme this package's own
     * {@see \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcher::resolveIndexName()}
     * already uses for the `page` index — kept deliberately separate (own method, own call site) rather
     * than generalized into a shared helper, since the two only share the naming SCHEME, not any actual
     * caller relationship.
     */
    protected function resolveEntityLookupSuggestIndexName(): string
    {
        $indexParameters = [
            $this->createSearchElasticsearchConfig()->getIndexPrefix(),
            $this->getStoreClient()->getCurrentStore()->getNameOrFail(),
            SharedSearchRankingConfig::ENTITY_LOOKUP_SUGGEST_INDEX_SOURCE_IDENTIFIER,
        ];

        return mb_strtolower(implode('_', array_filter($indexParameters)));
    }

    /**
     * @return array<\SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface>
     */
    public function getQueryAnalyzerPlugins(): array
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::PLUGINS_QUERY_ANALYZER);
    }

    /**
     * @return array<\SprykerCommunity\Client\SearchRanking\Intent\MsearchProbeRegistrarPluginInterface>
     */
    public function getMsearchProbeRegistrarPlugins(): array
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::PLUGINS_MSEARCH_PROBE_REGISTRAR);
    }
}
