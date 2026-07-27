<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Metric;

use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

class MetricWriter implements MetricWriterInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface $formulaEvaluator
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface $fitEvaluator
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingEntityManagerInterface $entityManager,
        protected FormulaEvaluatorInterface $formulaEvaluator,
        protected MetricFormulaFitEvaluatorInterface $fitEvaluator,
    ) {
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

        $previousMetricTransfer = $metricTransfer->getIdSearchRankingMetric() !== null
            ? $this->repository->findMetricById($metricTransfer->getIdSearchRankingMetric())
            : null;

        $savedMetricTransfer = $this->entityManager->saveMetric($metricTransfer);

        if ($this->hasAnyTrackedFieldChanged($previousMetricTransfer, $savedMetricTransfer)) {
            $this->recordHistory($savedMetricTransfer);
        }

        return $savedMetricTransfer;
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

    /**
     * Null $previousMetricTransfer means a brand-new metric — always worth an initial history row, since
     * there is nothing to compare it against yet.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer|null $previousMetricTransfer
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $currentMetricTransfer
     *
     * @return bool
     */
    protected function hasAnyTrackedFieldChanged(
        ?SearchRankingMetricTransfer $previousMetricTransfer,
        SearchRankingMetricTransfer $currentMetricTransfer,
    ): bool {
        if ($previousMetricTransfer === null) {
            return true;
        }

        return $previousMetricTransfer->getFormula() !== $currentMetricTransfer->getFormula()
            || $previousMetricTransfer->getWeight() !== $currentMetricTransfer->getWeight()
            || $previousMetricTransfer->getIsActive() !== $currentMetricTransfer->getIsActive()
            || $previousMetricTransfer->getIsHigherBetter() !== $currentMetricTransfer->getIsHigherBetter();
    }

    /**
     * Snapshots the metric's now-current config alongside its digest (if one exists yet — a brand-new
     * metric has none until the normalize cron has run at least once) and the fit quality of the new
     * formula against that digest, so a later drift-detection read can compare against exactly what was
     * true at the moment this change was made.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return void
     */
    protected function recordHistory(SearchRankingMetricTransfer $metricTransfer): void
    {
        $this->entityManager->recordMetricHistory($this->buildHistoryTransfer($metricTransfer, true));
    }

    /**
     * {@inheritDoc}
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return void
     */
    public function recordCheckOnly(SearchRankingMetricTransfer $metricTransfer): void
    {
        $this->entityManager->recordMetricHistory($this->buildHistoryTransfer($metricTransfer, false));
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param bool $isChange
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer
     */
    protected function buildHistoryTransfer(SearchRankingMetricTransfer $metricTransfer, bool $isChange): SearchRankingMetricHistoryTransfer
    {
        $historyTransfer = (new SearchRankingMetricHistoryTransfer())
            ->setFkSearchRankingMetric($metricTransfer->getIdSearchRankingMetricOrFail())
            ->setMetricName($metricTransfer->getNameOrFail())
            ->setWeight($metricTransfer->getWeightOrFail())
            ->setFormula($metricTransfer->getFormulaOrFail())
            ->setIsActive($metricTransfer->getIsActive() ?? true)
            ->setIsHigherBetter($metricTransfer->getIsHigherBetter() ?? true)
            ->setIsChange($isChange);

        $digestTransfer = $this->repository->findMetricDigest($metricTransfer->getIdSearchRankingMetricOrFail());

        if ($digestTransfer !== null) {
            $historyTransfer
                ->setMinValue($digestTransfer->getMinValue())
                ->setMaxValue($digestTransfer->getMaxValue())
                ->setMeanValue($digestTransfer->getMeanValue())
                ->setMedianValue($digestTransfer->getMedianValue())
                ->setSampleCount($digestTransfer->getSampleCount())
                ->setPercentiles($digestTransfer->getPercentiles())
                ->setFitRSquared($this->fitEvaluator->evaluateFit($metricTransfer->getFormulaOrFail(), $digestTransfer));
        }

        return $historyTransfer;
    }
}
