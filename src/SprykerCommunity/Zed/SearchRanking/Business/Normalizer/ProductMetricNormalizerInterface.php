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
     * `$storeName`/`$localeName` are an optional filter — `null` (the default) fans out over every
     * store×locale, matching every store's own available locales; a real value narrows to just that one
     * scope.
     *
     * @param string|null $storeName
     * @param string|null $localeName
     */
    public function normalize(?string $storeName = null, ?string $localeName = null): SearchRankingNormalizationResultTransfer;

    /**
     * Normalizes every product-metric row of the given metric, regardless of whether it is the random
     * tie-breaker metric normalize() itself skips.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function normalizeMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): int;
}
