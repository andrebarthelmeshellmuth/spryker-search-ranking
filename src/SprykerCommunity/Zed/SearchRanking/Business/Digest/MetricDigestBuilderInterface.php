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
     *
     * @return int The number of metrics a digest was (re)computed for.
     */
    public function rebuildDigests(): int;

    /**
     * Rebuilds one metric's distribution digest. Does nothing (returns false) when the metric has no
     * product-metric rows yet — there is nothing to summarize.
     *
     * @param int $idSearchRankingMetric
     *
     * @return bool
     */
    public function rebuildDigest(int $idSearchRankingMetric): bool;
}
