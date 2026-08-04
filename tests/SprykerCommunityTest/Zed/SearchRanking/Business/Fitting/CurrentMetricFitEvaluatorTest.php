<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Fitting;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\CurrentMetricFitEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Fitting
 * @group CurrentMetricFitEvaluatorTest
 * Add your own group annotations below this line
 */
class CurrentMetricFitEvaluatorTest extends Unit
{
    public function testReturnsNullWhenTheMetricDoesNotExist(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->expects($this->never())->method('evaluateFit');

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock);

        // Act
        $result = $evaluator->evaluate(7, 'DE', 'de_DE');

        // Assert
        $this->assertNull($result);
    }

    public function testReturnsNullWhenTheMetricHasNoDigestYet(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setFormula('x / max');

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn($metricTransfer);
        $repositoryMock->method('findMetricDigest')->with(7)->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->expects($this->never())->method('evaluateFit');

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock);

        // Act
        $result = $evaluator->evaluate(7, 'DE', 'de_DE');

        // Assert
        $this->assertNull($result);
    }

    public function testReturnsTheFitEvaluatorsResultForTheMetricsOwnFormulaAndDigest(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setFormula('x / (x + 6.42)');

        $digestTransfer = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(0.0)
            ->setMaxValue(100.0)
            ->setMeanValue(50.0)
            ->setMedianValue(50.0)
            ->setSampleCount(3)
            ->setPercentiles([0.0, 50.0, 100.0]);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn($metricTransfer);
        $repositoryMock->method('findMetricDigest')->with(7)->willReturn($digestTransfer);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->method('evaluateFit')->with('x / (x + 6.42)', $digestTransfer)->willReturn(0.87);

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock);

        // Act
        $result = $evaluator->evaluate(7, 'DE', 'de_DE');

        // Assert
        $this->assertSame(0.87, $result);
    }
}
