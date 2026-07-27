<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Preview;

use Generated\Shared\Transfer\SearchRankingFormulaPreviewPointTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\FormulaEvaluationException;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Backs the formula-preview AJAX endpoint: the server is the single source of truth for what a formula
 * evaluates to (the same `FormulaEvaluator` the normalization cron itself uses), the client only draws
 * whatever points come back — never a JS reimplementation of the expression-language math.
 */
class FormulaPreviewBuilder implements FormulaPreviewBuilderInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface $formulaEvaluator
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface $curveFitter
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected FormulaEvaluatorInterface $formulaEvaluator,
        protected NormalizationCurveFitterInterface $curveFitter,
    ) {
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $formula
     * @param bool $isHigherBetter
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer
     */
    public function buildPreview(int $idSearchRankingMetric, string $formula, bool $isHigherBetter): SearchRankingFormulaPreviewTransfer
    {
        $previewTransfer = new SearchRankingFormulaPreviewTransfer();
        $digestTransfer = $this->repository->findMetricDigest($idSearchRankingMetric);

        if ($digestTransfer === null) {
            return $previewTransfer->setErrorMessage(
                'No distribution digest yet for this metric — run search-ranking:normalize at least once after it has product-metric rows.',
            );
        }

        $baseVariables = [
            'min' => $digestTransfer->getMinValueOrFail(),
            'max' => $digestTransfer->getMaxValueOrFail(),
            'avg' => $digestTransfer->getMeanValueOrFail(),
            'count' => $digestTransfer->getSampleCountOrFail(),
        ];

        $percentiles = $digestTransfer->getPercentiles();
        $lastIndex = count($percentiles) - 1;

        foreach ($percentiles as $index => $x) {
            $previewTransfer->addCdfPoint(
                (new SearchRankingFormulaPreviewPointTransfer())->setXValue($x)->setYValue($index / $lastIndex),
            );

            try {
                $y = $this->formulaEvaluator->evaluate($formula, $baseVariables + ['x' => $x]);
            } catch (FormulaEvaluationException $exception) {
                return (new SearchRankingFormulaPreviewTransfer())->setErrorMessage($exception->getMessage());
            }

            $previewTransfer->addPoint(
                (new SearchRankingFormulaPreviewPointTransfer())->setXValue($x)->setYValue($y),
            );
        }

        foreach ($this->curveFitter->fit($digestTransfer, $isHigherBetter) as $candidateTransfer) {
            $previewTransfer->addCandidate($candidateTransfer);
        }

        return $previewTransfer;
    }
}
