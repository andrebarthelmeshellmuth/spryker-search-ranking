<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Fitting;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\RSquaredCalculator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\MathFunctionProvider;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Fitting
 * @group MetricFormulaFitEvaluatorTest
 * Add your own group annotations below this line
 */
class MetricFormulaFitEvaluatorTest extends Unit
{
    /**
     * `x / max` against a perfectly evenly-spaced (i.e. genuinely linear) digest must fit almost exactly —
     * this is the same synthetic-linear-digest technique NormalizationCurveFitterTest uses.
     *
     * @return void
     */
    public function testAGenuinelyLinearFormulaFitsAnEvenlySpacedDigestAlmostPerfectly(): void
    {
        // Arrange
        $evaluator = $this->createEvaluator();
        $percentiles = [];

        for ($i = 0; $i <= 100; $i++) {
            $percentiles[] = (float)$i;
        }

        $digestTransfer = $this->buildDigestTransfer($percentiles);

        // Act
        $rSquared = $evaluator->evaluateFit('x / max', $digestTransfer);

        // Assert
        $this->assertNotNull($rSquared);
        $this->assertGreaterThan(0.999, $rSquared);
    }

    /**
     * A formula that ignores its input entirely (constant output) explains none of the spread — this is
     * exactly what `random()`-style formulas would report, which is why they are excluded from any
     * fit-quality-based decision rather than measured by one.
     *
     * @return void
     */
    public function testAConstantFormulaFitsPoorly(): void
    {
        // Arrange
        $evaluator = $this->createEvaluator();
        $percentiles = [];

        for ($i = 0; $i <= 100; $i++) {
            $percentiles[] = (float)$i;
        }

        $digestTransfer = $this->buildDigestTransfer($percentiles);

        // Act
        $rSquared = $evaluator->evaluateFit('0.5', $digestTransfer);

        // Assert
        $this->assertNotNull($rSquared);
        $this->assertSame(0.0, $rSquared);
    }

    /**
     * @return void
     */
    public function testReturnsNullWhenTheFormulaFailsToEvaluate(): void
    {
        // Arrange
        $evaluator = $this->createEvaluator();
        $percentiles = [];

        for ($i = 0; $i <= 100; $i++) {
            $percentiles[] = (float)$i;
        }

        $digestTransfer = $this->buildDigestTransfer($percentiles);

        // Act
        $rSquared = $evaluator->evaluateFit('x / undefined_function_call()', $digestTransfer);

        // Assert
        $this->assertNull($rSquared);
    }

    /**
     * A digest with at most one percentile point (a brand-new or single-sample metric) has no spread to
     * fit a curve against at all — must report "can't evaluate" rather than a divide-by-zero or a
     * meaningless single-point "perfect" fit.
     *
     * @return void
     */
    public function testReturnsNullWhenTheDigestHasAtMostOnePercentilePoint(): void
    {
        // Arrange
        $evaluator = $this->createEvaluator();
        $digestTransfer = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(5.0)
            ->setMaxValue(5.0)
            ->setMeanValue(5.0)
            ->setMedianValue(5.0)
            ->setSampleCount(1)
            ->setPercentiles([5.0]);

        // Act
        $rSquared = $evaluator->evaluateFit('x / max', $digestTransfer);

        // Assert
        $this->assertNull($rSquared);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluator
     */
    protected function createEvaluator(): MetricFormulaFitEvaluator
    {
        $config = new SearchRankingConfig();

        return new MetricFormulaFitEvaluator(
            new FormulaEvaluator(new MathFunctionProvider(), $config),
            new RSquaredCalculator(),
        );
    }

    /**
     * @param array<float> $percentiles Ascending, exactly 101 entries (indices 0..100).
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer
     */
    protected function buildDigestTransfer(array $percentiles): SearchRankingMetricDigestTransfer
    {
        return (new SearchRankingMetricDigestTransfer())
            ->setMinValue($percentiles[0])
            ->setMaxValue($percentiles[100])
            ->setMeanValue(array_sum($percentiles) / count($percentiles))
            ->setMedianValue($percentiles[50])
            ->setSampleCount(1000)
            ->setPercentiles($percentiles);
    }
}
