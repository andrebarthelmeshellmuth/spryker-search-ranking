<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business;

use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\MathFunctionProvider;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriter;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizer;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface;
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
            $this->getEntityManager(),
            $this->createFormulaEvaluator(),
        );
    }
}
