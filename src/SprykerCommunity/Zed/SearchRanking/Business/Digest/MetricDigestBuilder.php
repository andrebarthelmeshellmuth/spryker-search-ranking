<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Digest;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use InvalidArgumentException;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Precomputes a fixed-size distribution digest of each metric's raw_value column — a 101-point
 * empirical-CDF backbone (percentiles 0,1,2,...,100) plus min/max/mean/median — so the
 * normalization-authoring GUI never touches the raw per-product rows directly, however many there are.
 * Runs as a byproduct of the same `search-ranking:normalize` cron invocation that already normalizes
 * every row, on its own schedule rather than sharing that scan: percentile computation needs every value
 * sorted in memory, a different access pattern from the normalizer's keyset-paginated batch stream, so
 * keeping the two decoupled is simpler than threading digest state through that loop.
 */
class MetricDigestBuilder implements MetricDigestBuilderInterface
{
    /**
     * 100 steps -> 101 inclusive points (0, 1, 2, ..., 100), each one percentile apart.
     *
     * @var int
     */
    protected const PERCENTILE_STEP_COUNT = 100;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface $storeFacade
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingEntityManagerInterface $entityManager,
        protected SearchRankingToStoreFacadeInterface $storeFacade,
    ) {
    }

    /**
     * @param string|null $filterStoreName
     * @param string|null $filterLocaleName
     */
    public function rebuildDigests(?string $filterStoreName = null, ?string $filterLocaleName = null): int
    {
        $processedCount = 0;

        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            $storeName = $storeTransfer->getNameOrFail();

            if ($filterStoreName !== null && $storeName !== $filterStoreName) {
                continue;
            }

            foreach ($storeTransfer->getAvailableLocaleIsoCodes() as $localeName) {
                if ($filterLocaleName !== null && $localeName !== $filterLocaleName) {
                    continue;
                }

                foreach ($this->repository->getActiveMetricCollection($storeName, $localeName)->getMetrics() as $metricTransfer) {
                    if (!$this->rebuildDigest($metricTransfer->getIdSearchRankingMetricOrFail(), $storeName, $localeName)) {
                        continue;
                    }

                    $processedCount++;
                }
            }
        }

        return $processedCount;
    }

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function rebuildDigest(int $idSearchRankingMetric, string $storeName, string $localeName): bool
    {
        $rawValues = $this->repository->getRawValues($idSearchRankingMetric, $storeName, $localeName);

        if ($rawValues === []) {
            return false;
        }

        $digestTransfer = $this->buildDigest($rawValues)
            ->setFkSearchRankingMetric($idSearchRankingMetric)
            ->setStoreName($storeName)
            ->setLocaleName($localeName);
        $this->entityManager->saveMetricDigest($digestTransfer);

        return true;
    }

    /**
     * Pure computation, deliberately public: no repository/entity-manager access, so it is unit-testable
     * directly with a plain array.
     *
     * Sorts the given raw values once, then computes min/max/mean/median plus the percentile backbone via
     * linear interpolation between closest ranks — the same method `numpy.percentile()` defaults to.
     *
     * @param array<float> $rawValues
     *
     * @throws \InvalidArgumentException
     */
    public function buildDigest(array $rawValues): SearchRankingMetricDigestTransfer
    {
        // rebuildDigest() already guards this before calling in, but this method is deliberately public
        // for direct unit-testability -- an empty array here would otherwise silently produce a digest
        // with min/max null and a NAN mean (0/0) instead of failing loudly.
        if ($rawValues === []) {
            throw new InvalidArgumentException('$rawValues must not be empty.');
        }

        sort($rawValues);
        $count = count($rawValues);

        $percentiles = [];

        for ($percentileStep = 0; $percentileStep <= static::PERCENTILE_STEP_COUNT; $percentileStep++) {
            $percentiles[] = $this->percentile($rawValues, $percentileStep / static::PERCENTILE_STEP_COUNT);
        }

        return (new SearchRankingMetricDigestTransfer())
            ->setMinValue($rawValues[0])
            ->setMaxValue($rawValues[$count - 1])
            ->setMeanValue(array_sum($rawValues) / $count)
            ->setMedianValue($this->percentile($rawValues, 0.5))
            ->setSampleCount($count)
            ->setPercentiles($percentiles);
    }

    /**
     * @param array<int, float> $sortedValues
     * @param float $percentile
     */
    protected function percentile(array $sortedValues, float $percentile): float
    {
        $lastIndex = count($sortedValues) - 1;

        if ($lastIndex === 0) {
            return $sortedValues[0];
        }

        $rank = $percentile * $lastIndex;
        $lowerIndex = (int)floor($rank);
        $upperIndex = (int)ceil($rank);

        if ($lowerIndex === $upperIndex) {
            return $sortedValues[$lowerIndex];
        }

        $fraction = $rank - $lowerIndex;

        return $sortedValues[$lowerIndex] + $fraction * ($sortedValues[$upperIndex] - $sortedValues[$lowerIndex]);
    }
}
