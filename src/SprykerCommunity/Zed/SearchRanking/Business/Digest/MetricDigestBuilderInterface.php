<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Digest;

interface MetricDigestBuilderInterface
{
    /**
     * Rebuilds the distribution digest of every ACTIVE metric from its current raw_value rows.
     * `$storeName`/`$localeName` are an optional filter — `null` (the default) fans out over every
     * store×locale; a real value narrows to just that one scope.
     *
     * @param string|null $storeName
     * @param string|null $localeName
     *
     * @return int The number of metrics a digest was (re)computed for.
     */
    public function rebuildDigests(?string $storeName = null, ?string $localeName = null): int;

    /**
     * Rebuilds one metric's distribution digest. Does nothing (returns false) when the metric has no
     * product-metric rows yet — there is nothing to summarize.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function rebuildDigest(int $idSearchRankingMetric, string $storeName, string $localeName): bool;
}
