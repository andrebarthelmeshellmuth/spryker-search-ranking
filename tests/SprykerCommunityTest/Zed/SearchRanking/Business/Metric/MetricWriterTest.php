<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Metric;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriter;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;

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
        $entityManagerMock->expects($this->once())
            ->method('saveMetric')
            ->with($metricTransfer)
            ->willReturn($savedMetricTransfer);

        // Act
        $resultTransfer = (new MetricWriter($entityManagerMock, $formulaEvaluatorMock))->saveMetric($metricTransfer);

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

        // Assert
        $this->expectException(InvalidFormulaException::class);
        $this->expectExceptionMessage('Unexpected end of expression');

        // Act
        (new MetricWriter($entityManagerMock, $formulaEvaluatorMock))->saveMetric($metricTransfer);
    }

    /**
     * @return void
     */
    public function testDeletesTheMetricByIdViaTheEntityManager(): void
    {
        // Arrange
        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('deleteMetric')->with(9);

        // Act
        (new MetricWriter($entityManagerMock, $formulaEvaluatorMock))->deleteMetric(9);
    }
}
