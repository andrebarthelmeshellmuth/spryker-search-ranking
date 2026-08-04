<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Fitting;

use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

class CurrentMetricFitEvaluator implements CurrentMetricFitEvaluatorInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface $fitEvaluator
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected MetricFormulaFitEvaluatorInterface $fitEvaluator,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function evaluate(int $idSearchRankingMetric, string $storeName, string $localeName): ?float
    {
        $metricTransfer = $this->repository->findMetricById($idSearchRankingMetric, $storeName, $localeName);

        if ($metricTransfer === null) {
            return null;
        }

        $digestTransfer = $this->repository->findMetricDigest($idSearchRankingMetric, $storeName, $localeName);

        if ($digestTransfer === null) {
            return null;
        }

        return $this->fitEvaluator->evaluateFit($metricTransfer->getFormulaOrFail(), $digestTransfer);
    }
}
