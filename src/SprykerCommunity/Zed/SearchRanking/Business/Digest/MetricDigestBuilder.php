<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Digest;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Precomputes a fixed-size distribution digest of each metric's raw_value column — a 101-point
 * empirical-CDF backbone (percentiles 0,1,2,...,100) plus min/max/mean/median — so the
 * normalization-authoring GUI (phase 4.5) never touches the raw per-product rows directly, however many
 * there are. Runs as a byproduct of the same `search-ranking:normalize` cron invocation that already
 * normalizes every row, on its own schedule rather than sharing that scan: percentile computation needs
 * every value sorted in memory, a different access pattern from the normalizer's keyset-paginated batch
 * stream, so keeping the two decoupled is simpler than threading digest state through that loop.
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
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface
     */
    protected SearchRankingRepositoryInterface $repository;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface
     */
    protected SearchRankingEntityManagerInterface $entityManager;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     */
    public function __construct(
        SearchRankingRepositoryInterface $repository,
        SearchRankingEntityManagerInterface $entityManager,
    ) {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }

    /**
     * @return int
     */
    public function rebuildDigests(): int
    {
        $processedCount = 0;

        foreach ($this->repository->getActiveMetricCollection()->getMetrics() as $metricTransfer) {
            if (!$this->rebuildDigest($metricTransfer->getIdSearchRankingMetricOrFail())) {
                continue;
            }

            $processedCount++;
        }

        return $processedCount;
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return bool
     */
    public function rebuildDigest(int $idSearchRankingMetric): bool
    {
        $rawValues = $this->repository->getRawValues($idSearchRankingMetric);

        if ($rawValues === []) {
            return false;
        }

        $digestTransfer = $this->buildDigest($rawValues)->setFkSearchRankingMetric($idSearchRankingMetric);
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
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer
     */
    public function buildDigest(array $rawValues): SearchRankingMetricDigestTransfer
    {
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
     *
     * @return float
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
