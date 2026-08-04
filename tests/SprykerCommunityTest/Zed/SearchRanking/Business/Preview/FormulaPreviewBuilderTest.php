<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Preview;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\FormulaEvaluationException;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Preview\FormulaPreviewBuilder;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Preview
 * @group FormulaPreviewBuilderTest
 * Add your own group annotations below this line
 */
class FormulaPreviewBuilderTest extends Unit
{
    public function testReturnsAnErrorMessageWhenNoDigestExistsYet(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricDigest')->with(5)->willReturn(null);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->expects($this->never())->method('evaluate');

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->expects($this->never())->method('fit');

        $builder = new FormulaPreviewBuilder($repositoryMock, $formulaEvaluatorMock, $curveFitterMock);

        // Act
        $previewTransfer = $builder->buildPreview(5, 'x / (x + k)', true, 'DE', 'de_DE');

        // Assert
        $this->assertSame(
            'No distribution digest yet for this metric — run search-ranking:normalize at least once after it has product-metric rows.',
            $previewTransfer->getErrorMessage(),
        );
        $this->assertCount(0, $previewTransfer->getPoints());
        $this->assertCount(0, $previewTransfer->getCdfPoints());
    }

    public function testBuildsCdfAndFormulaPointsFromTheDigestPercentilesAndIncludesCurveFitCandidates(): void
    {
        // Arrange
        $digestTransfer = $this->buildDigestTransfer([10.0, 20.0, 30.0]);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricDigest')->with(5)->willReturn($digestTransfer);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock
            ->method('evaluate')
            /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter */
            ->willReturnCallback(fn (string $formula, array $variables): float => $variables['x'] / $variables['max']);

        $candidateTransfer = (new SearchRankingCurveFitCandidateTransfer())
            ->setName('x / (x + k)')
            ->setFormula('x / (x + 15)')
            ->setParameterValue(15.0)
            ->setRSquared(0.9)
            ->setIsWinner(true);

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->method('fit')->with($digestTransfer, true)->willReturn([$candidateTransfer]);

        $builder = new FormulaPreviewBuilder($repositoryMock, $formulaEvaluatorMock, $curveFitterMock);

        // Act
        $previewTransfer = $builder->buildPreview(5, 'x / max', true, 'DE', 'de_DE');

        // Assert
        $this->assertNull($previewTransfer->getErrorMessage());

        $cdfPoints = $previewTransfer->getCdfPoints();
        $this->assertCount(3, $cdfPoints);
        $this->assertSame(10.0, $cdfPoints[0]->getXValue());
        $this->assertSame(0.0, $cdfPoints[0]->getYValue());
        $this->assertSame(20.0, $cdfPoints[1]->getXValue());
        $this->assertSame(0.5, $cdfPoints[1]->getYValue());
        $this->assertSame(30.0, $cdfPoints[2]->getXValue());
        $this->assertSame(1.0, $cdfPoints[2]->getYValue());

        $points = $previewTransfer->getPoints();
        $this->assertCount(3, $points);
        $this->assertSame(10.0 / 30.0, $points[0]->getYValue());
        $this->assertSame(1.0, $points[2]->getYValue());

        $this->assertSame([$candidateTransfer], $previewTransfer->getCandidates()->getArrayCopy());
    }

    public function testReturnsAFreshErrorTransferWhenFormulaEvaluationFailsPartway(): void
    {
        // Arrange
        $digestTransfer = $this->buildDigestTransfer([10.0, 20.0, 30.0]);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricDigest')->with(5)->willReturn($digestTransfer);

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock
            ->method('evaluate')
            ->willReturnOnConsecutiveCalls(
                1.0,
                $this->throwException(new FormulaEvaluationException('Unknown function "bogus".')),
            );

        $curveFitterMock = $this->createMock(NormalizationCurveFitterInterface::class);
        $curveFitterMock->expects($this->never())->method('fit');

        $builder = new FormulaPreviewBuilder($repositoryMock, $formulaEvaluatorMock, $curveFitterMock);

        // Act
        $previewTransfer = $builder->buildPreview(5, 'bogus(x)', true, 'DE', 'de_DE');

        // Assert: a brand-new transfer is returned on failure — any cdf/points already added before the
        // failing evaluation are discarded, not surfaced as a half-populated chart.
        $this->assertSame('Unknown function "bogus".', $previewTransfer->getErrorMessage());
        $this->assertCount(0, $previewTransfer->getPoints());
        $this->assertCount(0, $previewTransfer->getCdfPoints());
        $this->assertCount(0, $previewTransfer->getCandidates());
    }

    /**
     * @param array<float> $percentiles
     */
    protected function buildDigestTransfer(array $percentiles): SearchRankingMetricDigestTransfer
    {
        return (new SearchRankingMetricDigestTransfer())
            ->setMinValue($percentiles[0])
            ->setMaxValue($percentiles[count($percentiles) - 1])
            ->setMeanValue(array_sum($percentiles) / count($percentiles))
            ->setSampleCount(count($percentiles))
            ->setPercentiles($percentiles);
    }
}
