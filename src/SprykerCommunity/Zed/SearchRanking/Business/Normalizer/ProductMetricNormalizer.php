<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Normalizer;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;
use Throwable;

class ProductMetricNormalizer implements ProductMetricNormalizerInterface
{
    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface
     */
    protected SearchRankingRepositoryInterface $repository;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface
     */
    protected SearchRankingEntityManagerInterface $entityManager;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface
     */
    protected FormulaEvaluatorInterface $formulaEvaluator;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig
     */
    protected SearchRankingConfig $config;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface $formulaEvaluator
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     */
    public function __construct(
        SearchRankingRepositoryInterface $repository,
        SearchRankingEntityManagerInterface $entityManager,
        FormulaEvaluatorInterface $formulaEvaluator,
        SearchRankingConfig $config,
    ) {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
        $this->formulaEvaluator = $formulaEvaluator;
        $this->config = $config;
    }

    /**
     * @return \Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer
     */
    public function normalize(): SearchRankingNormalizationResultTransfer
    {
        $resultTransfer = (new SearchRankingNormalizationResultTransfer())
            ->setProcessedMetricCount(0)
            ->setUpdatedRowCount(0);

        foreach ($this->repository->getActiveMetricCollection()->getMetrics() as $metricTransfer) {
            try {
                $updatedRowCount = $this->normalizeMetric($metricTransfer);
            } catch (Throwable $throwable) {
                $resultTransfer->addError(
                    sprintf('Metric "%s" skipped: %s', $metricTransfer->getName(), $throwable->getMessage()),
                );

                continue;
            }

            $resultTransfer->setProcessedMetricCount($resultTransfer->getProcessedMetricCount() + 1);
            $resultTransfer->setUpdatedRowCount($resultTransfer->getUpdatedRowCount() + $updatedRowCount);
        }

        return $resultTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return int
     */
    protected function normalizeMetric(SearchRankingMetricTransfer $metricTransfer): int
    {
        $idMetric = $metricTransfer->getIdSearchRankingMetricOrFail();
        $statisticsTransfer = $this->repository->getMetricStatistics($idMetric);

        if (!$statisticsTransfer->getCount()) {
            return 0;
        }

        $baseVariables = [
            'min' => $statisticsTransfer->getMinValue(),
            'max' => $statisticsTransfer->getMaxValue(),
            'avg' => $statisticsTransfer->getAvgValue(),
            'count' => $statisticsTransfer->getCount(),
        ];

        $formula = $metricTransfer->getFormulaOrFail();
        $batchSize = $this->config->getNormalizationBatchSize();
        $updatedRowCount = 0;
        $idLastProductMetric = 0;

        while (true) {
            $productMetricTransfers = $this->repository->getProductMetricBatch($idMetric, $idLastProductMetric, $batchSize);

            if ($productMetricTransfers === []) {
                break;
            }

            $normalizedValuesByIdProductMetric = [];

            foreach ($productMetricTransfers as $productMetricTransfer) {
                $idProductMetric = $productMetricTransfer->getIdSearchRankingProductMetricOrFail();
                $variables = $baseVariables + ['x' => $productMetricTransfer->getRawValueOrFail()];

                $normalizedValuesByIdProductMetric[$idProductMetric] = $this->clamp(
                    $this->formulaEvaluator->evaluate($formula, $variables),
                );

                $idLastProductMetric = $idProductMetric;
            }

            $this->entityManager->updateNormalizedValues($normalizedValuesByIdProductMetric);
            $updatedRowCount += count($normalizedValuesByIdProductMetric);
        }

        return $updatedRowCount;
    }

    /**
     * @param float $value
     *
     * @return float
     */
    protected function clamp(float $value): float
    {
        return min(max($value, $this->config->getNormalizedValueMinimum()), 1.0);
    }
}
