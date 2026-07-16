<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Metric;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;

class MetricWriter implements MetricWriterInterface
{
    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface
     */
    protected SearchRankingEntityManagerInterface $entityManager;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface
     */
    protected FormulaEvaluatorInterface $formulaEvaluator;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface $formulaEvaluator
     */
    public function __construct(
        SearchRankingEntityManagerInterface $entityManager,
        FormulaEvaluatorInterface $formulaEvaluator,
    ) {
        $this->entityManager = $entityManager;
        $this->formulaEvaluator = $formulaEvaluator;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer
    {
        $validationResponseTransfer = $this->formulaEvaluator->validate($metricTransfer->getFormulaOrFail());

        if (!$validationResponseTransfer->getIsSuccess()) {
            throw new InvalidFormulaException((string)$validationResponseTransfer->getErrorMessage());
        }

        return $this->entityManager->saveMetric($metricTransfer);
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return void
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $this->entityManager->deleteMetric($idSearchRankingMetric);
    }
}
