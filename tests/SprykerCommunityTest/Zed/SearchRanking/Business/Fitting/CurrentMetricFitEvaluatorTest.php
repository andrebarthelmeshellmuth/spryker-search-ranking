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
use Generated\Shared\Transfer\StoreTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\CurrentMetricFitEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
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
 * @group Portable
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

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock, $this->createStoreFacadeMock());

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

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock, $this->createStoreFacadeMock());

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

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock, $this->createStoreFacadeMock());

        // Act
        $result = $evaluator->evaluate(7, 'DE', 'de_DE');

        // Assert
        $this->assertSame(0.87, $result);
    }

    /**
     * The diagnostic this method exists for: prove it genuinely calls evaluate() once per real locale of
     * the store, not just the store's own default -- two locales, two different digests, two different
     * fit results, keyed correctly.
     */
    public function testEvaluateAcrossLocalesReturnsOneFitPerRealLocaleOfTheStore(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setFormula('x / (x + 6.42)');

        $digestTransferDe = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(0.0)->setMaxValue(100.0)->setMeanValue(50.0)->setMedianValue(50.0)
            ->setSampleCount(3)->setPercentiles([0.0, 50.0, 100.0]);
        $digestTransferEn = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(0.0)->setMaxValue(200.0)->setMeanValue(90.0)->setMedianValue(90.0)
            ->setSampleCount(5)->setPercentiles([0.0, 90.0, 200.0]);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn($metricTransfer);
        $repositoryMock->method('findMetricDigest')->willReturnMap([
            [7, 'DE', 'de_DE', $digestTransferDe],
            [7, 'DE', 'en_US', $digestTransferEn],
        ]);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->method('evaluateFit')->willReturnMap([
            ['x / (x + 6.42)', $digestTransferDe, 0.91],
            ['x / (x + 6.42)', $digestTransferEn, 0.42],
        ]);

        $storeFacadeMock = $this->createMock(SearchRankingToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())->setName('DE')->setAvailableLocaleIsoCodes(['de_DE', 'en_US']),
        ]);

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock, $storeFacadeMock);

        // Act
        $result = $evaluator->evaluateAcrossLocales(7, 'DE');

        // Assert
        $this->assertSame(['de_DE' => 0.91, 'en_US' => 0.42], $result);
    }

    /**
     * A metric with isLocaleScoped=true genuinely diverges per locale in the database itself -- this
     * proves evaluate() honors each locale's own real formula (via the repository's own locale-filtered
     * lookup, not some special-cased branch here), not just each locale's own digest as the sibling test
     * above already covers for the isLocaleScoped=false case.
     */
    public function testEvaluateAcrossLocalesUsesEachLocalesOwnFormulaWhenTheMetricIsLocaleScoped(): void
    {
        // Arrange
        $metricTransferDe = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setIsLocaleScoped(true)
            ->setFormula('2 * atan(x)');
        $metricTransferEn = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setIsLocaleScoped(true)
            ->setFormula('3 * atan(x)');

        $digestTransferDe = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(0.0)->setMaxValue(100.0)->setMeanValue(50.0)->setMedianValue(50.0)
            ->setSampleCount(3)->setPercentiles([0.0, 50.0, 100.0]);
        $digestTransferEn = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(0.0)->setMaxValue(100.0)->setMeanValue(50.0)->setMedianValue(50.0)
            ->setSampleCount(3)->setPercentiles([0.0, 50.0, 100.0]);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturnMap([
            [7, 'DE', 'de_DE', $metricTransferDe],
            [7, 'DE', 'en_US', $metricTransferEn],
        ]);
        $repositoryMock->method('findMetricDigest')->willReturnMap([
            [7, 'DE', 'de_DE', $digestTransferDe],
            [7, 'DE', 'en_US', $digestTransferEn],
        ]);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->method('evaluateFit')->willReturnMap([
            ['2 * atan(x)', $digestTransferDe, 0.95],
            ['3 * atan(x)', $digestTransferEn, 0.31],
        ]);

        $storeFacadeMock = $this->createMock(SearchRankingToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())->setName('DE')->setAvailableLocaleIsoCodes(['de_DE', 'en_US']),
        ]);

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock, $storeFacadeMock);

        // Act
        $result = $evaluator->evaluateAcrossLocales(7, 'DE');

        // Assert
        $this->assertSame(['de_DE' => 0.95, 'en_US' => 0.31], $result);
    }

    public function testEvaluateAcrossLocalesKeepsALocaleWithNoDigestYetAsNullRatherThanOmittingIt(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setFormula('x / (x + 6.42)');

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn($metricTransfer);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->expects($this->never())->method('evaluateFit');

        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock, $this->createStoreFacadeMock());

        // Act
        $result = $evaluator->evaluateAcrossLocales(7, 'DE');

        // Assert
        $this->assertSame(['de_DE' => null], $result);
    }

    public function testEvaluateAcrossLocalesReturnsAnEmptyArrayForAnUnknownStore(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $evaluator = new CurrentMetricFitEvaluator($repositoryMock, $fitEvaluatorMock, $this->createStoreFacadeMock());

        // Act
        $result = $evaluator->evaluateAcrossLocales(7, 'NOT-A-REAL-STORE');

        // Assert
        $this->assertSame([], $result);
    }

    protected function createStoreFacadeMock(): SearchRankingToStoreFacadeInterface
    {
        $storeFacadeMock = $this->createMock(SearchRankingToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())->setName('DE')->setAvailableLocaleIsoCodes(['de_DE']),
        ]);

        return $storeFacadeMock;
    }
}
