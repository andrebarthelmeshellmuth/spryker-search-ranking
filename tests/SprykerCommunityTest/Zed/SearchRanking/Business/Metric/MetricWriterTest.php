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
use Generated\Shared\Transfer\StoreTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingEvents;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriter;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
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
        $resultTransfer = (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');

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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');
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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');
    }

    public function testDefaultsChangeSourceToManualWhenTheSubmittedMetricDoesNotSetOne(): void
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
            ->with($this->callback(fn (SearchRankingMetricHistoryTransfer $historyTransfer): bool => $historyTransfer->getChangeSource() === 'manual'))
            ->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn(null);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');
    }

    /**
     * An automated caller (e.g. search-ranking-optimizer's Auto-Tune runner) sets changeSource explicitly
     * on the submitted transfer -- that value, not the manual default, must reach the history row.
     */
    public function testRecordsAnExplicitlySubmittedChangeSource(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('pdp_impressions')
            ->setWeight(0.3)
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setChangeSource('auto_tune');

        $savedMetricTransfer = (clone $metricTransfer)->setIdSearchRankingMetric(11);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('saveMetric')->willReturn($savedMetricTransfer);
        $entityManagerMock->expects($this->once())
            ->method('recordMetricHistory')
            ->with($this->callback(fn (SearchRankingMetricHistoryTransfer $historyTransfer): bool => $historyTransfer->getChangeSource() === 'auto_tune'))
            ->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn(null);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');
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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($resubmittedMetricTransfer, 'DE', 'de_DE');
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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($updatedMetricTransfer, 'DE', 'de_DE');
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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');
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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');
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
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');
    }

    public function testTriggersRankingConfigurationChangeEventWhenAMetricIsSaved(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('saveMetric')->willReturn((clone $metricTransfer)->setIdSearchRankingMetric(7));
        $entityManagerMock->method('recordMetricHistory')->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn(null);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->expects($this->once())->method('trigger')->with(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $eventFacadeMock, $this->createStoreFacadeMock()))
            ->saveMetric($metricTransfer, 'DE', 'de_DE');
    }

    public function testTriggersRankingConfigurationChangeEventWhenAMetricWeightIsSaved(): void
    {
        // Arrange
        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricWeight')->willReturn(0.3);
        $repositoryMock->method('findMetricById')->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);

        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->expects($this->once())->method('trigger')->with(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $eventFacadeMock, $this->createStoreFacadeMock()))
            ->saveMetricWeight(7, 'DE', 'de_DE', 0.5);
    }

    public function testTriggersRankingConfigurationChangeEventWhenAMetricIsDeleted(): void
    {
        // Arrange
        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);

        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->expects($this->once())->method('trigger')->with(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $eventFacadeMock, $this->createStoreFacadeMock()))
            ->deleteMetric(9);
    }

    /**
     * recordCheckOnly() doesn't change any config value — it's a monitoring-only audit row (the auto-tune
     * job checking a metric's fit without updating it) — so it must never trigger a republish.
     */
    public function testDoesNotTriggerRankingConfigurationChangeEventOnRecordCheckOnly(): void
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

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);

        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->expects($this->never())->method('trigger');

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $eventFacadeMock, $this->createStoreFacadeMock()))
            ->recordCheckOnly($metricTransfer, 'DE', 'de_DE');
    }

    /**
     * A metric marked NOT locale-scoped (a store-wide fact like sales/stock) fans a single weight write
     * out to every real locale of the store, instead of writing just the one locale it was called with.
     */
    public function testFansOutTheWeightWriteToEveryStoreLocaleWhenTheMetricIsNotLocaleScoped(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setIsLocaleScoped(false);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7, 'DE', 'de_DE')->willReturn($metricTransfer);
        $repositoryMock->method('findMetricWeight')->willReturn(0.2);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $savedLocaleNames = [];
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(2))
            ->method('saveMetricWeight')
            ->with(7, 'DE', $this->callback(function (string $localeName) use (&$savedLocaleNames): bool {
                $savedLocaleNames[] = $localeName;

                return true;
            }), 0.6);
        $entityManagerMock->expects($this->exactly(2))->method('recordMetricHistory')->willReturnArgument(0);

        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->expects($this->once())->method('trigger')->with(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $eventFacadeMock, $this->createMultiLocaleStoreFacadeMock()))
            ->saveMetricWeight(7, 'DE', 'de_DE', 0.6);

        // Assert
        $this->assertSame(['de_DE', 'en_US'], $savedLocaleNames);
    }

    /**
     * A metric marked locale-scoped (the default) only ever writes the one locale saveMetricWeight() was
     * called with, even though the store has several locales configured.
     */
    public function testOnlyWritesTheGivenLocaleWhenTheMetricIsLocaleScoped(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('pdp_impressions')
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setIsLocaleScoped(true);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn($metricTransfer);
        $repositoryMock->method('findMetricWeight')->willReturn(0.2);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('saveMetricWeight')->with(7, 'DE', 'de_DE', 0.6);
        $entityManagerMock->expects($this->once())->method('recordMetricHistory')->willReturnArgument(0);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createMultiLocaleStoreFacadeMock()))
            ->saveMetricWeight(7, 'DE', 'de_DE', 0.6);
    }

    /**
     * The formula/isActive/shape write side of the SAME fan-out mechanism
     * {@see testFansOutTheWeightWriteToEveryStoreLocaleWhenTheMetricIsNotLocaleScoped()} already proves
     * for weight — both read isLocaleScoped through {@see MetricWriter::resolveEffectiveWeightLocales()},
     * so they can never disagree about which locales a write touches.
     */
    public function testFansOutTheFormulaWriteToEveryStoreLocaleWhenTheMetricIsNotLocaleScoped(): void
    {
        // Arrange
        $existingMetricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setIsLocaleScoped(false);

        $submittedMetricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setFormula('x / avg')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setIsLocaleScoped(false);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn($existingMetricTransfer);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $savedLocaleNames = [];
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(2))
            ->method('saveMetric')
            ->with($submittedMetricTransfer, 'DE', $this->callback(function (string $localeName) use (&$savedLocaleNames): bool {
                $savedLocaleNames[] = $localeName;

                return true;
            }))
            ->willReturn($submittedMetricTransfer);
        $entityManagerMock->expects($this->exactly(2))->method('recordMetricHistory')->willReturnArgument(0);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createMultiLocaleStoreFacadeMock()))
            ->saveMetric($submittedMetricTransfer, 'DE', 'de_DE');

        // Assert
        $this->assertSame(['de_DE', 'en_US'], $savedLocaleNames);
    }

    /**
     * The formula/isActive/shape side of {@see testOnlyWritesTheGivenLocaleWhenTheMetricIsLocaleScoped()}.
     */
    public function testOnlyWritesTheGivenLocalesFormulaWhenTheMetricIsLocaleScoped(): void
    {
        // Arrange
        $existingMetricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('pdp_impressions')
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setIsLocaleScoped(true);

        $submittedMetricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('pdp_impressions')
            ->setFormula('x / avg')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setIsLocaleScoped(true);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn($existingMetricTransfer);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('saveMetric')->with($submittedMetricTransfer, 'DE', 'de_DE')->willReturn($submittedMetricTransfer);
        $entityManagerMock->expects($this->once())->method('recordMetricHistory')->willReturnArgument(0);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createMultiLocaleStoreFacadeMock()))
            ->saveMetric($submittedMetricTransfer, 'DE', 'de_DE');
    }

    /**
     * A metric that can't be found at all (shouldn't happen in practice) is treated the same as
     * locale-scoped — write just the passed-in locale, never guess a fan-out from an unknown metric.
     */
    public function testOnlyWritesTheGivenLocaleWhenTheMetricCannotBeFound(): void
    {
        // Arrange
        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn(null);
        $repositoryMock->method('findMetricWeight')->willReturn(0.2);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('saveMetricWeight')->with(7, 'DE', 'de_DE', 0.6);
        $entityManagerMock->expects($this->never())->method('recordMetricHistory');

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createMultiLocaleStoreFacadeMock()))
            ->saveMetricWeight(7, 'DE', 'de_DE', 0.6);
    }

    /**
     * A metric with no store-config row yet for the target store (e.g. Scope Copy bootstrapping a
     * brand-new market) comes back from {@see SearchRankingRepositoryInterface::findMetricById()} with a
     * null formula — {@see SearchRankingRepository::attachStoreConfig()}'s documented "safe absence".
     * The weight itself must still be written, but no history row can be built (the history table's
     * formula column is NOT NULL) — regression test for the real bug where this crashed with "Property
     * formula of transfer SearchRankingMetricTransfer is null" when using Scope Copy's "Copy now" onto a
     * store never configured for that metric.
     */
    public function testDoesNotRecordHistoryWhenTheMetricHasNoFormulaForTheTargetStoreYet(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')->setFormula()
            ->setIsActive(false);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7, 'AT', 'de_AT')->willReturn($metricTransfer);
        $repositoryMock->method('findMetricWeight')->willReturn(0.2);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('saveMetricWeight')->with(7, 'AT', 'de_AT', 0.9);
        $entityManagerMock->expects($this->never())->method('recordMetricHistory');

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetricWeight(7, 'AT', 'de_AT', 0.9);
    }

    /**
     * The independent lookup {@see ScopeConfigCopier} uses to know a not-locale-scoped weight write's
     * real blast radius BEFORE making it — must return every real locale of the store, same set
     * saveMetricWeight()'s own fan-out would actually write to.
     */
    public function testResolveEffectiveWeightLocalesReturnsEveryStoreLocaleWhenNotLocaleScoped(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setIsLocaleScoped(false);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7, 'DE', 'de_DE')->willReturn($metricTransfer);

        // Act
        $localeNames = (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createMultiLocaleStoreFacadeMock()))
            ->resolveEffectiveWeightLocales(7, 'DE', 'de_DE');

        // Assert
        $this->assertSame(['de_DE', 'en_US'], $localeNames);
    }

    /**
     * A locale-scoped metric's write never fans out — the given locale is the only effective one.
     */
    public function testResolveEffectiveWeightLocalesReturnsOnlyTheGivenLocaleWhenLocaleScoped(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('pdp_impressions')
            ->setIsLocaleScoped(true);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn($metricTransfer);

        // Act
        $localeNames = (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createMultiLocaleStoreFacadeMock()))
            ->resolveEffectiveWeightLocales(7, 'DE', 'de_DE');

        // Assert
        $this->assertSame(['de_DE'], $localeNames);
    }

    /**
     * A metric that can't be found at all is treated the same as locale-scoped — never guess a fan-out
     * from an unknown metric.
     */
    public function testResolveEffectiveWeightLocalesReturnsOnlyTheGivenLocaleWhenMetricCannotBeFound(): void
    {
        // Arrange
        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->willReturn(null);

        // Act
        $localeNames = (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createMultiLocaleStoreFacadeMock()))
            ->resolveEffectiveWeightLocales(7, 'DE', 'de_DE');

        // Assert
        $this->assertSame(['de_DE'], $localeNames);
    }

    /**
     * isLocaleScoped is a tracked field just like isHigherBetter — changing it alone (formula/isActive/
     * isHigherBetter unchanged) must still produce a history row.
     */
    public function testRecordsHistoryWhenOnlyIsLocaleScopedChanged(): void
    {
        // Arrange
        $previousMetricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setIsLocaleScoped(true);

        $resubmittedMetricTransfer = (clone $previousMetricTransfer)->setIsLocaleScoped(false);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->willReturn((new SearchRankingFormulaValidationResponseTransfer())->setIsSuccess(true));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('saveMetric')->willReturn(clone $resubmittedMetricTransfer);
        $entityManagerMock->expects($this->once())
            ->method('recordMetricHistory')
            ->with($this->callback(fn (SearchRankingMetricHistoryTransfer $historyTransfer): bool => $historyTransfer->getIsLocaleScoped() === false))
            ->willReturnArgument(0);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricById')->with(7)->willReturn($previousMetricTransfer);
        $repositoryMock->method('findMetricDigest')->willReturn(null);

        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);
        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->willReturn([]);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock, $curveFitterMock, $this->createMock(SearchRankingToEventFacadeInterface::class), $this->createStoreFacadeMock()))
            ->saveMetric($resubmittedMetricTransfer, 'DE', 'de_DE');
    }

    /**
     * No configured store matches the scope these tests save into — resolveLocaleNamesForStore() falls
     * back to just the single locale passed to saveMetric(), preserving every test's existing
     * once-per-history-write expectation.
     */
    protected function createStoreFacadeMock(): SearchRankingToStoreFacadeInterface
    {
        $storeFacadeMock = $this->createMock(SearchRankingToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([]);

        return $storeFacadeMock;
    }

    /**
     * A DE store with two real locales — used by the saveMetricWeight() fan-out tests to prove a
     * not-locale-scoped write reaches every one of them, and a locale-scoped write still doesn't.
     */
    protected function createMultiLocaleStoreFacadeMock(): SearchRankingToStoreFacadeInterface
    {
        $storeFacadeMock = $this->createMock(SearchRankingToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())->setName('DE')->setAvailableLocaleIsoCodes(['de_DE', 'en_US']),
        ]);

        return $storeFacadeMock;
    }
}
