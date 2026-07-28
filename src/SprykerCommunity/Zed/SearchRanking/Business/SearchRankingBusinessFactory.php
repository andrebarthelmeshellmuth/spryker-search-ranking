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
use SprykerCommunity\Zed\SearchRanking\SearchRankingDependencyProvider;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface getRepository()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface getEntityManager()
 */
class SearchRankingBusinessFactory extends AbstractBusinessFactory
{
    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface
     */
    public function createFormulaEvaluator(): FormulaEvaluatorInterface
    {
        return new FormulaEvaluator(
            $this->createMathFunctionProvider(),
            $this->getConfig(),
        );
    }

    /**
     * @return \Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface
     */
    public function createMathFunctionProvider(): ExpressionFunctionProviderInterface
    {
        return new MathFunctionProvider();
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface
     */
    public function createProductMetricNormalizer(): ProductMetricNormalizerInterface
    {
        return new ProductMetricNormalizer(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->createFormulaEvaluator(),
            $this->getConfig(),
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface
     */
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

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Metric\WeightNormalizerInterface
     */
    public function createWeightNormalizer(): WeightNormalizerInterface
    {
        return new WeightNormalizer(
            $this->getRepository(),
            $this->getEntityManager(),
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoaderInterface
     */
    public function createScoresPageDataLoader(): ScoresPageDataLoaderInterface
    {
        return new ScoresPageDataLoader($this->getRepository());
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisherInterface
     */
    public function createProductAbstractScorePublisher(): ProductAbstractScorePublisherInterface
    {
        return new ProductAbstractScorePublisher(
            $this->getRepository(),
            $this->getEventFacade(),
            $this->getConfig(),
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface
     */
    public function createSettingManager(): SettingManagerInterface
    {
        return new SettingManager(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->getConfig(),
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface
     */
    public function getEventFacade(): SearchRankingToEventFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_EVENT);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface
     */
    public function getSearchRankingClient(): SearchRankingToSearchRankingClientInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::CLIENT_SEARCH_RANKING);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Compatibility\CompatibilityCheckerInterface
     */
    public function createCompatibilityChecker(): CompatibilityCheckerInterface
    {
        return new CompatibilityChecker($this->getSearchRankingClient());
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilderInterface
     */
    public function createMetricDigestBuilder(): MetricDigestBuilderInterface
    {
        return new MetricDigestBuilder(
            $this->getRepository(),
            $this->getEntityManager(),
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface
     */
    public function createNormalizationCurveFitter(): NormalizationCurveFitterInterface
    {
        return new NormalizationCurveFitter($this->createRSquaredCalculator());
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Fitting\RSquaredCalculator
     */
    public function createRSquaredCalculator(): RSquaredCalculator
    {
        return new RSquaredCalculator();
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface
     */
    public function createMetricFormulaFitEvaluator(): MetricFormulaFitEvaluatorInterface
    {
        return new MetricFormulaFitEvaluator(
            $this->createFormulaEvaluator(),
            $this->createRSquaredCalculator(),
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Fitting\CurrentMetricFitEvaluatorInterface
     */
    public function createCurrentMetricFitEvaluator(): CurrentMetricFitEvaluatorInterface
    {
        return new CurrentMetricFitEvaluator(
            $this->getRepository(),
            $this->createMetricFormulaFitEvaluator(),
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Preview\FormulaPreviewBuilderInterface
     */
    public function createFormulaPreviewBuilder(): FormulaPreviewBuilderInterface
    {
        return new FormulaPreviewBuilder(
            $this->getRepository(),
            $this->createFormulaEvaluator(),
            $this->createNormalizationCurveFitter(),
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizerInterface
     */
    public function createMetricRandomizer(): MetricRandomizerInterface
    {
        return new MetricRandomizer(
            $this->getRepository(),
            $this->createProductMetricNormalizer(),
            $this->createProductAbstractScorePublisher(),
            $this->getConfig()->getRandomMetricName(),
        );
    }
}
