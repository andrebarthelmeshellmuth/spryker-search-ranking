<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business;

use Spryker\Shared\SearchElasticsearch\SearchElasticsearchConfig as SharedSearchElasticsearchConfig;
use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig as ZedSearchElasticsearchConfig;
use SprykerCommunity\Client\SearchRanking\Semantic\TextEmbeddingsInferenceClient;
use SprykerCommunity\Zed\SearchRanking\Business\Client\ElasticaClientProvider;
use SprykerCommunity\Zed\SearchRanking\Business\Client\ElasticaClientProviderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Compatibility\CompatibilityChecker;
use SprykerCommunity\Zed\SearchRanking\Business\Compatibility\CompatibilityCheckerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Configuration\ConfigurationReader;
use SprykerCommunity\Zed\SearchRanking\Business\Configuration\ConfigurationReaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilder;
use SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Embedding\EmbeddingGenerator;
use SprykerCommunity\Zed\SearchRanking\Business\Embedding\EmbeddingGeneratorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\CurrentMetricFitEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\CurrentMetricFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitter;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\RSquaredCalculator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\MathFunctionProvider;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\BrandCorpusReader;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\BrandCorpusReaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\CategoryCorpusReader;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\CategoryCorpusReaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupIncrementalSyncer;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupIncrementalSyncerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupSuggestIndexNameResolver;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupSuggestIndexNameResolverInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\ProductAbstractStoreResolver;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\ProductAbstractStoreResolverInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SkuCorpusReader;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SkuCorpusReaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupIndexer;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupIndexerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupRebuilder;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupRebuilderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriter;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\WeightNormalizer;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\WeightNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizer;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\EmbeddingPageDataLoader;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\EmbeddingPageDataLoaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ProductEmbeddingFinder;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ProductEmbeddingFinderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoader;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Preview\FormulaPreviewBuilder;
use SprykerCommunity\Zed\SearchRanking\Business\Preview\FormulaPreviewBuilderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisher;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisherInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizer;
use SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\FullScopeCopier;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\FullScopeCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopier;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockManager;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockValidator;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockValidatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopier;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManager;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Specificity\SpecificityWeightingStatusChecker;
use SprykerCommunity\Zed\SearchRanking\Business\Specificity\SpecificityWeightingStatusCheckerInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToStorageClientInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToLocaleFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingDependencyProvider;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface getRepository()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface getEntityManager()
 */
class SearchRankingBusinessFactory extends AbstractBusinessFactory
{
    public function createFormulaEvaluator(): FormulaEvaluatorInterface
    {
        return new FormulaEvaluator(
            $this->createMathFunctionProvider(),
            $this->getConfig(),
        );
    }

    public function createMathFunctionProvider(): ExpressionFunctionProviderInterface
    {
        return new MathFunctionProvider();
    }

    public function createProductMetricNormalizer(): ProductMetricNormalizerInterface
    {
        return new ProductMetricNormalizer(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->createFormulaEvaluator(),
            $this->getConfig(),
            $this->getStoreFacade(),
        );
    }

    public function createMetricWriter(): MetricWriterInterface
    {
        return new MetricWriter(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->createFormulaEvaluator(),
            $this->createMetricFormulaFitEvaluator(),
            $this->createNormalizationCurveFitter(),
            $this->getEventFacade(),
            $this->getStoreFacade(),
        );
    }

    public function createWeightNormalizer(): WeightNormalizerInterface
    {
        return new WeightNormalizer(
            $this->getRepository(),
            $this->createMetricWriter(),
        );
    }

    public function createScoresPageDataLoader(): ScoresPageDataLoaderInterface
    {
        return new ScoresPageDataLoader($this->getRepository());
    }

    public function createEmbeddingPageDataLoader(): EmbeddingPageDataLoaderInterface
    {
        return new EmbeddingPageDataLoader($this->getRepository(), $this->getConfig());
    }

    public function createProductEmbeddingFinder(): ProductEmbeddingFinderInterface
    {
        return new ProductEmbeddingFinder($this->getRepository(), $this->getConfig());
    }

    public function createEmbeddingClient(): TextEmbeddingsInferenceClient
    {
        return new TextEmbeddingsInferenceClient($this->getConfig()->getEmbeddingServiceUrl());
    }

    public function createEmbeddingGenerator(): EmbeddingGeneratorInterface
    {
        return new EmbeddingGenerator(
            $this->createEmbeddingClient(),
            $this->getRepository(),
            $this->getEntityManager(),
            $this->getStoreFacade(),
            $this->getConfig(),
        );
    }

    public function createProductAbstractScorePublisher(): ProductAbstractScorePublisherInterface
    {
        return new ProductAbstractScorePublisher(
            $this->getRepository(),
            $this->getEventFacade(),
            $this->getConfig(),
        );
    }

    public function createSettingManager(): SettingManagerInterface
    {
        return new SettingManager(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->getConfig(),
            $this->getEventFacade(),
        );
    }

    public function createConfigurationReader(): ConfigurationReaderInterface
    {
        return new ConfigurationReader(
            $this->getRepository(),
            $this->createSettingManager(),
            $this->getConfig(),
        );
    }

    public function getEventFacade(): SearchRankingToEventFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_EVENT);
    }

    public function getSearchRankingClient(): SearchRankingToSearchRankingClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_SEARCH_RANKING);
    }

    public function createCompatibilityChecker(): CompatibilityCheckerInterface
    {
        return new CompatibilityChecker($this->getSearchRankingClient());
    }

    public function createSpecificityWeightingStatusChecker(): SpecificityWeightingStatusCheckerInterface
    {
        return new SpecificityWeightingStatusChecker($this->getSearchRankingClient());
    }

    public function createMetricDigestBuilder(): MetricDigestBuilderInterface
    {
        return new MetricDigestBuilder(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->getStoreFacade(),
        );
    }

    public function createNormalizationCurveFitter(): NormalizationCurveFitterInterface
    {
        return new NormalizationCurveFitter($this->createRSquaredCalculator());
    }

    public function createRSquaredCalculator(): RSquaredCalculator
    {
        return new RSquaredCalculator();
    }

    public function createMetricFormulaFitEvaluator(): MetricFormulaFitEvaluatorInterface
    {
        return new MetricFormulaFitEvaluator(
            $this->createFormulaEvaluator(),
            $this->createRSquaredCalculator(),
        );
    }

    public function createCurrentMetricFitEvaluator(): CurrentMetricFitEvaluatorInterface
    {
        return new CurrentMetricFitEvaluator(
            $this->getRepository(),
            $this->createMetricFormulaFitEvaluator(),
            $this->getStoreFacade(),
        );
    }

    public function createFormulaPreviewBuilder(): FormulaPreviewBuilderInterface
    {
        return new FormulaPreviewBuilder(
            $this->getRepository(),
            $this->createFormulaEvaluator(),
            $this->createNormalizationCurveFitter(),
        );
    }

    public function createMetricRandomizer(): MetricRandomizerInterface
    {
        return new MetricRandomizer(
            $this->getRepository(),
            $this->createProductMetricNormalizer(),
            $this->createProductAbstractScorePublisher(),
            $this->getStoreFacade(),
            $this->getConfig()->getRandomMetricName(),
        );
    }

    public function getStoreFacade(): SearchRankingToStoreFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_STORE);
    }

    public function getStorageClient(): SearchRankingToStorageClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_STORAGE);
    }

    public function createSkuCorpusReader(): SkuCorpusReaderInterface&EntityCorpusReaderPluginInterface
    {
        return new SkuCorpusReader();
    }

    public function createBrandCorpusReader(): BrandCorpusReaderInterface&EntityCorpusReaderPluginInterface
    {
        return new BrandCorpusReader();
    }

    public function createCategoryCorpusReader(): CategoryCorpusReaderInterface&EntityCorpusReaderPluginInterface
    {
        return new CategoryCorpusReader();
    }

    public function createSuggestIndexEntityLookupRebuilder(): SuggestIndexEntityLookupRebuilderInterface
    {
        return new SuggestIndexEntityLookupRebuilder(
            $this->getEntityCorpusReaders(),
            $this->createSuggestIndexEntityLookupIndexer(),
            $this->getStoreFacade(),
            $this->getLocaleFacade(),
            $this->createEntityLookupSuggestIndexNameResolver(),
        );
    }

    /**
     * Backs {@see \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface::syncEntityLookupForProductAbstracts()}
     * — the event-pipeline near-live sync mode counterpart to {@see createSuggestIndexEntityLookupRebuilder()}'s
     * cron/manual full rebuild.
     */
    public function createEntityLookupIncrementalSyncer(): EntityLookupIncrementalSyncerInterface
    {
        return new EntityLookupIncrementalSyncer(
            $this->getEntityCorpusReaders(),
            $this->createSuggestIndexEntityLookupIndexer(),
            $this->getStoreFacade(),
            $this->createEntityLookupSuggestIndexNameResolver(),
            $this->createProductAbstractStoreResolver(),
        );
    }

    public function createEntityLookupSuggestIndexNameResolver(): EntityLookupSuggestIndexNameResolverInterface
    {
        return new EntityLookupSuggestIndexNameResolver($this->createSearchElasticsearchConfig());
    }

    public function createProductAbstractStoreResolver(): ProductAbstractStoreResolverInterface
    {
        return new ProductAbstractStoreResolver();
    }

    /**
     * The active {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface}
     * stack for {@see createSuggestIndexEntityLookupRebuilder()} — this package's own built-in default
     * (sku/brand/category) plus whatever a project registered via
     * {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingDependencyProvider::PLUGINS_ENTITY_CORPUS_READER}.
     * The SAME "built-ins + array_merge(DependencyProvider plugins)" shape this package's Client-layer
     * {@see \SprykerCommunity\Client\SearchRanking\SearchRankingFactory::getQueryAnalyzers()} already uses.
     *
     * @return array<\SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface>
     */
    public function getEntityCorpusReaders(): array
    {
        return array_merge(
            [
                $this->createSkuCorpusReader(),
                $this->createBrandCorpusReader(),
                $this->createCategoryCorpusReader(),
            ],
            $this->getEntityCorpusReaderPlugins(),
        );
    }

    /**
     * @return array<\SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface>
     */
    public function getEntityCorpusReaderPlugins(): array
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::PLUGINS_ENTITY_CORPUS_READER);
    }

    public function createSuggestIndexEntityLookupIndexer(): SuggestIndexEntityLookupIndexerInterface
    {
        return new SuggestIndexEntityLookupIndexer($this->createElasticaClientProvider()->getClient());
    }

    public function createElasticaClientProvider(): ElasticaClientProviderInterface
    {
        return new ElasticaClientProvider($this->createZedSearchElasticsearchConfig());
    }

    public function createSearchElasticsearchConfig(): SharedSearchElasticsearchConfig
    {
        return new SharedSearchElasticsearchConfig();
    }

    public function createZedSearchElasticsearchConfig(): ZedSearchElasticsearchConfig
    {
        return new ZedSearchElasticsearchConfig();
    }

    public function getLocaleFacade(): SearchRankingToLocaleFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_LOCALE);
    }

    public function createScopeConfigCopier(): ScopeConfigCopierInterface
    {
        return new ScopeConfigCopier(
            $this->getRepository(),
            $this->createMetricWriter(),
            $this->createSettingManager(),
        );
    }

    public function createScopeCopyLockValidator(): ScopeCopyLockValidatorInterface
    {
        return new ScopeCopyLockValidator($this->getRepository());
    }

    public function createScopeCopyLockManager(): ScopeCopyLockManagerInterface
    {
        return new ScopeCopyLockManager(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->createScopeCopyLockValidator(),
            $this->createScopeConfigCopier(),
            $this->createFullScopeCopier(),
        );
    }

    public function createStoreConfigCopier(): StoreConfigCopierInterface
    {
        return new StoreConfigCopier(
            $this->getRepository(),
            $this->createMetricWriter(),
        );
    }

    public function createFullScopeCopier(): FullScopeCopierInterface
    {
        return new FullScopeCopier(
            $this->createScopeConfigCopier(),
            $this->createStoreConfigCopier(),
        );
    }
}
