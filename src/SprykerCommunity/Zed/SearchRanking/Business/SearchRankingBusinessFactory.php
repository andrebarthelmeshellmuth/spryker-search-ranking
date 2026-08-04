<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business;

use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use SprykerCommunity\Zed\SearchRanking\Business\Compatibility\CompatibilityChecker;
use SprykerCommunity\Zed\SearchRanking\Business\Compatibility\CompatibilityCheckerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilder;
use SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilderInterface;
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
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriter;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\WeightNormalizer;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\WeightNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizer;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoader;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Preview\FormulaPreviewBuilder;
use SprykerCommunity\Zed\SearchRanking\Business\Preview\FormulaPreviewBuilderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisher;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisherInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizer;
use SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManager;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface;
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
}
