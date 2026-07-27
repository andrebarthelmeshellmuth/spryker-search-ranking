<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Metric;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface;
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
    /**
     * @return void
     */
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

        // Act
        $resultTransfer = (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock))
            ->saveMetric($metricTransfer);

        // Assert
        $this->assertSame(7, $resultTransfer->getIdSearchRankingMetric());
    }

    /**
     * @return void
     */
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

        // Assert
        $this->expectException(InvalidFormulaException::class);
        $this->expectExceptionMessage('Unexpected end of expression');

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock))
            ->saveMetric($metricTransfer);
    }

    /**
     * @return void
     */
    public function testDeletesTheMetricByIdViaTheEntityManager(): void
    {
        // Arrange
        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $fitEvaluatorMock = $this->createMock(MetricFormulaFitEvaluatorInterface::class);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('deleteMetric')->with(9);

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock))
            ->deleteMetric(9);
    }

    /**
     * A brand-new metric (no previous state to compare against) always gets an initial history row.
     *
     * @return void
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

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock))
            ->saveMetric($metricTransfer);
    }

    /**
     * Saving a metric with NOTHING tracked actually changed (same formula/weight/isActive/isHigherBetter
     * as before) must not write a redundant history row.
     *
     * @return void
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

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock))
            ->saveMetric($resubmittedMetricTransfer);
    }

    /**
     * When the formula actually changes AND a digest already exists, the history row must carry the
     * digest snapshot and the new formula's fit quality against it.
     *
     * @return void
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

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock))
            ->saveMetric($updatedMetricTransfer);
    }

    /**
     * Used by the auto-tune job (search-ranking-optimizer) when a metric's fit was checked but no update
     * was applied — must append an isChange=false row, unlike every other history-recording path here,
     * which always records isChange=true.
     *
     * @return void
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

        // Act
        (new MetricWriter($repositoryMock, $entityManagerMock, $formulaEvaluatorMock, $fitEvaluatorMock))
            ->recordCheckOnly($metricTransfer);
    }
}
