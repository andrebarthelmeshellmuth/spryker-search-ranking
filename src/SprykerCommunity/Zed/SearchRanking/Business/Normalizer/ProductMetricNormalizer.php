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
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;
use Throwable;

class ProductMetricNormalizer implements ProductMetricNormalizerInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface $formulaEvaluator
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface $storeFacade
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingEntityManagerInterface $entityManager,
        protected FormulaEvaluatorInterface $formulaEvaluator,
        protected SearchRankingConfig $config,
        protected SearchRankingToStoreFacadeInterface $storeFacade,
    ) {
    }

    /**
     * @param string|null $filterStoreName
     * @param string|null $filterLocaleName
     */
    public function normalize(?string $filterStoreName = null, ?string $filterLocaleName = null): SearchRankingNormalizationResultTransfer
    {
        $resultTransfer = (new SearchRankingNormalizationResultTransfer())
            ->setProcessedMetricCount(0)
            ->setUpdatedRowCount(0);

        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            $storeName = $storeTransfer->getNameOrFail();

            if ($filterStoreName !== null && $storeName !== $filterStoreName) {
                continue;
            }

            foreach ($storeTransfer->getAvailableLocaleIsoCodes() as $localeName) {
                if ($filterLocaleName !== null && $localeName !== $filterLocaleName) {
                    continue;
                }

                $this->normalizeStoreLocale($resultTransfer, $storeName, $localeName);
            }
        }

        return $resultTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer $resultTransfer
     * @param string $storeName
     * @param string $localeName
     */
    protected function normalizeStoreLocale(
        SearchRankingNormalizationResultTransfer $resultTransfer,
        string $storeName,
        string $localeName,
    ): void {
        foreach ($this->repository->getActiveMetricCollection($storeName, $localeName)->getMetrics() as $metricTransfer) {
            if ($metricTransfer->getName() === $this->config->getRandomMetricName()) {
                continue;
            }

            try {
                $updatedRowCount = $this->normalizeMetric($metricTransfer, $storeName, $localeName);
            } catch (Throwable $throwable) {
                $resultTransfer->addError(
                    sprintf(
                        'Metric "%s" skipped for %s/%s: %s',
                        $metricTransfer->getName(),
                        $storeName,
                        $localeName,
                        $throwable->getMessage(),
                    ),
                );

                continue;
            }

            $resultTransfer->setProcessedMetricCount($resultTransfer->getProcessedMetricCount() + 1);
            $resultTransfer->setUpdatedRowCount($resultTransfer->getUpdatedRowCount() + $updatedRowCount);
        }
    }

    /**
     * Exposed publicly (not just used internally by {@see normalize()}'s loop) so
     * {@see \SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizer} can re-normalize
     * ONE metric — the random tie-breaker — on its own nightly cadence, independent of this hourly
     * per-active-metric loop, which deliberately skips it (see {@see SearchRankingConfig::getRandomMetricName()}).
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function normalizeMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): int
    {
        $idMetric = $metricTransfer->getIdSearchRankingMetricOrFail();
        $statisticsTransfer = $this->repository->getMetricStatistics($idMetric, $storeName, $localeName);

        if (!$statisticsTransfer->getCount()) {
            return 0;
        }

        $baseVariables = [
            'min' => $statisticsTransfer->getMinValueOrFail(),
            'max' => $statisticsTransfer->getMaxValueOrFail(),
            'avg' => $statisticsTransfer->getAvgValueOrFail(),
            'count' => $statisticsTransfer->getCountOrFail(),
        ];

        $formula = $metricTransfer->getFormulaOrFail();
        $batchSize = $this->config->getNormalizationBatchSize();
        $updatedRowCount = 0;
        $idLastProductMetric = 0;

        while (true) {
            $productMetricTransfers = $this->repository->getProductMetricBatch(
                $idMetric,
                $storeName,
                $localeName,
                $idLastProductMetric,
                $batchSize,
            );

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
     */
    protected function clamp(float $value): float
    {
        return min(max($value, $this->config->getNormalizedValueMinimum()), 1.0);
    }
}
