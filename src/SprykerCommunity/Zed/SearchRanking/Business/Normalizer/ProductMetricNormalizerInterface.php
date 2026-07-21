<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Normalizer;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;

interface ProductMetricNormalizerInterface
{
    /**
     * Normalizes every ACTIVE metric except the configured random tie-breaker metric (see
     * {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getRandomMetricName()}) — that one is
     * refreshed on its own nightly cadence by
     * {@see \SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizerInterface} instead.
     *
     * @return \Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer
     */
    public function normalize(): SearchRankingNormalizationResultTransfer;

    /**
     * Normalizes every product-metric row of the given metric, regardless of whether it is the random
     * tie-breaker metric normalize() itself skips.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return int
     */
    public function normalizeMetric(SearchRankingMetricTransfer $metricTransfer): int;
}
