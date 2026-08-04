<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Metric;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriter;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Metric
 * @group MetricWriterTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class MetricWriterTest extends Unit
{
    public function testPersistsTheMetricWhenItsFormulaValidatesSuccessfully(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')
            ->with('x / max')
            ->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $savedMetricTransfer = (clone $metricTransfer)->setIdSearchRankingMetric(7);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('saveMetric')->with($metricTransfer)->willReturn($savedMetricTransfer);
        $entityManagerMock->method('recordMetricHistory')->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn(null);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Act
        $resultTransfer = (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->saveMetric($metricTransfer);

        // Assert
        $this->assertSame(7, $resultTransfer->getIdSearchRankingMetric());
    }

    public function testThrowsAndNeverPersistsWhenTheFormulaFailsValidation(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('broken_metric')
            ->setWeight(0.5)
            ->setFormula('atan(x')
            ->setIsActive(true);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')
            ->with('atan(x')
            ->willReturn(
                (new SearchRankingFormulaValidationResponseTransfer())
                    ->setIsSuccess(false)
                    ->setErrorMessage('Unexpected end of expression'),
            );

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('saveMetric');
        $entityManagerMock->expects($this->never())->method('recordMetricHistory');

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Assert
        $this->expectException(InvalidFormulaException::class);
        $this->expectExceptionMessage('Unexpected end of expression');

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->saveMetric($metricTransfer);
    }

    public function testDeletesTheMetricByIdViaTheEntityManager(): void
    {
        // Arrange
        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('deleteMetric')->with(9);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->deleteMetric(9);
    }

    /**
     * A brand-new metric (no previous state to compare against) always gets an initial history row.
     */
    public function testRecordsAnInitialHistoryRowForABrandNewMetric(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('pdp_impressions')
            ->setWeight(0.3)
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true);

        $savedMetricTransfer = (clone $metricTransfer)->setIdSearchRankingMetric(11);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('saveMetric')->willReturn($savedMetricTransfer);
        $entityManagerMock->expects($this->once())
            ->method('recordMetricHistory')
            ->with($this->callback(fn (SearchRankingMetricHistoryTransfer $historyTransfer): bool => $historyTransfer->getFkSearchRankingMetric() === 11
                && $historyTransfer->getMetricName() === 'pdp_impressions'
                && $historyTransfer->getFormula() === 'x / max'
                && $historyTransfer->getIsChange() === true))
            ->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn(null);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->expects($this->never())->method('evaluateFit');

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->saveMetric($metricTransfer);
    }

    /**
     * Saving a metric with NOTHING tracked actually changed (same formula/weight/isActive/isHigherBetter
     * as before) must not write a redundant history row.
     */
    public function testDoesNotRecordHistoryWhenNoTrackedFieldActuallyChanged(): void
    {
        // Arrange
        $previousMetricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true);

        $resubmittedMetricTransfer = clone $previousMetricTransfer;

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('saveMetric')->willReturn(clone $previousMetricTransfer);
        $entityManagerMock->expects($this->never())->method('recordMetricHistory');

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn($previousMetricTransfer);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->saveMetric($resubmittedMetricTransfer);
    }

    /**
     * When the formula actually changes AND a digest already exists, the history row must carry the
     * digest snapshot and the new formula's fit quality against it.
     */
    public function testRecordsTheDigestSnapshotAndFitQualityWhenAFormulaChangeHasAnExistingDigest(): void
    {
        // Arrange
        $previousMetricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true);

        $updatedMetricTransfer = (clone $previousMetricTransfer)->setFormula('atan(x / 100) / (pi() / 2)');
        $savedMetricTransfer = clone $updatedMetricTransfer;

        $digestTransfer = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(0.0)
            ->setMaxValue(100.0)
            ->setMeanValue(50.0)
            ->setMedianValue(50.0)
            ->setSampleCount(3)
            ->setPercentiles([0.0, 50.0, 100.0]);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('saveMetric')->willReturn($savedMetricTransfer);
        $entityManagerMock->expects($this->once())
            ->method('recordMetricHistory')
            ->with($this->callback(fn (SearchRankingMetricHistoryTransfer $historyTransfer): bool => $historyTransfer->getFormula() === 'atan(x / 100) / (pi() / 2)'
                && $historyTransfer->getSampleCount() === 3
                && $historyTransfer->getPercentiles() === [0.0, 50.0, 100.0]
                && $historyTransfer->getFitRSquared() === 0.75))
            ->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn($previousMetricTransfer);
        $repositoryMock->method('findMetricDigest')->with(7)->willReturn($digestTransfer);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->method('evaluateFit')->with('atan(x / 100) / (pi() / 2)', $digestTransfer)->willReturn(0.75);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->saveMetric($updatedMetricTransfer);
    }

    /**
     * Used by the auto-tune job (search-ranking-optimizer) when a metric's fit was checked but no update
     * was applied — must append an isChange=false row, unlike every other history-recording path here,
     * which always records isChange=true.
     */
    public function testRecordCheckOnlyAppendsAnIsChangeFalseHistoryRow(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('recordMetricHistory')
            ->with($this->callback(fn (SearchRankingMetricHistoryTransfer $historyTransfer): bool => $historyTransfer->getFkSearchRankingMetric() === 7
                && $historyTransfer->getMetricName() === 'top_seller'
                && $historyTransfer->getIsChange() === false));

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->recordCheckOnly($metricTransfer, 'DE', 'de_DE');
    }

    /**
     * A saved formula that byte-for-byte matches one of a fresh fit's candidates gets that candidate's
     * `shape` slug persisted onto it.
     */
    public function testSaveMetricSetsShapeWhenTheFormulaMatchesAFitCandidate(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('x / (x + 6.42)')
            ->setIsActive(true)
            ->setIsHigherBetter(true);

        $digestTransfer = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(0.0)
            ->setMaxValue(100.0)
            ->setMeanValue(50.0)
            ->setMedianValue(50.0)
            ->setSampleCount(3)
            ->setPercentiles([0.0, 50.0, 100.0]);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveMetric')
            ->with($this->callback(fn (SearchRankingMetricTransfer $transfer): bool => $transfer->getShape() === 'hyperbolic'))
            ->willReturnArgument(0);
        $entityManagerMock->method('recordMetricHistory')->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn(null);
        $repositoryMock->method('findMetricDigest')->with(7)->willReturn($digestTransfer);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->method('evaluateFit')->willReturn(0.9);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->with($digestTransfer, true)->willReturn([
            (new SearchRankingCurveFitCandidateTransfer())
                ->setName('atan(x / k) / (pi / 2)')
                ->setShape('atan')
                ->setFormula('atan(x / 5) / (pi() / 2)')
                ->setParameterValue(5.0)
                ->setRSquared(0.8)
                ->setIsWinner(false),
            (new SearchRankingCurveFitCandidateTransfer())
                ->setName('x / (x + k)')
                ->setShape('hyperbolic')
                ->setFormula('x / (x + 6.42)')
                ->setParameterValue(6.42)
                ->setRSquared(0.9)
                ->setIsWinner(true),
        ]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->saveMetric($metricTransfer);
    }

    /**
     * A freeform/custom formula that matches no fit candidate leaves `shape` null — a safe, expected
     * outcome, not an error.
     */
    public function testSaveMetricLeavesShapeNullWhenTheFormulaMatchesNoFitCandidate(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('(x + 1) / (x + 2)')
            ->setIsActive(true)
            ->setIsHigherBetter(true);

        $digestTransfer = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(0.0)
            ->setMaxValue(100.0)
            ->setMeanValue(50.0)
            ->setMedianValue(50.0)
            ->setSampleCount(3)
            ->setPercentiles([0.0, 50.0, 100.0]);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveMetric')
            ->with($this->callback(fn (SearchRankingMetricTransfer $transfer): bool => $transfer->getShape() === null))
            ->willReturnArgument(0);
        $entityManagerMock->method('recordMetricHistory')->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn(null);
        $repositoryMock->method('findMetricDigest')->with(7)->willReturn($digestTransfer);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $fitEvaluatorMock->method('evaluateFit')->willReturn(0.5);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->with($digestTransfer, true)->willReturn([
            (new SearchRankingCurveFitCandidateTransfer())
                ->setName('x / (x + k)')
                ->setShape('hyperbolic')
                ->setFormula('x / (x + 6.42)')
                ->setParameterValue(6.42)
                ->setRSquared(0.9)
                ->setIsWinner(true),
        ]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->saveMetric($metricTransfer);
    }

    /**
     * A brand-new metric (no id yet) has no digest to derive a shape from at all — shape stays null,
     * without even attempting a digest lookup.
     */
    public function testSaveMetricLeavesShapeNullForABrandNewMetricWithNoIdYet(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('brand_new_metric')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true);

        $savedMetricTransfer = (clone $metricTransfer)->setIdSearchRankingMetric(42);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveMetric')
            ->with($this->callback(fn (SearchRankingMetricTransfer $transfer): bool => $transfer->getShape() === null))
            ->willReturn($savedMetricTransfer);
        $entityManagerMock->method('recordMetricHistory')->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn(null);
        // Called once, by history recording AFTER save (the saved transfer has id=42 by then) — never by
        // shape detection, which skips the lookup entirely for a pre-save transfer with no id yet.
        $repositoryMock->expects($this->once())->method('findMetricDigest')->with(42)->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->expects($this->never())->method('fit');

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock))
            ->saveMetric($metricTransfer);
    }
}
