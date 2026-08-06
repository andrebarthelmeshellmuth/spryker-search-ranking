<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingStoreConfigCopyResultTransfer;

interface StoreConfigCopierInterface
{
    /**
     * @var string
     */
    public const MODE_MIRROR = 'mirror';

    /**
     * @var string
     */
    public const MODE_COPY_ONLY_OVERLAP = 'copy_only_overlap';

    /**
     * Copies formula/isActive/shape ({@see \Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer})
     * for every metric EXPLICITLY configured in the source STORE onto the target store — store-only,
     * unlike {@see ScopeConfigCopierInterface::copyScopeConfiguration()}'s (store,locale)-scoped weight/
     * setting copy, since formula/isActive/shape are themselves store-scoped, not locale-scoped.
     * `$sourceLocaleName`/`$targetLocaleName` are used
     * ONLY as the digest lens {@see \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface::saveMetric()}
     * re-detects each copied metric's `shape` against (its own real fit-quality metadata, not carried
     * over verbatim) — never part of the copy's own scope key.
     *
     * `MODE_MIRROR` (default): copies every metric the source has explicitly configured, creating a new
     * target row for one the target has never configured at all — matches this feature's existing
     * bootstrap philosophy (see {@see ScopeConfigCopierInterface}).
     * `MODE_COPY_ONLY_OVERLAP`: conservative, opt-in — only overwrites a metric the TARGET has already
     * independently configured; one the target has never touched is left alone (counted in
     * `skippedCount`, not an error). The resulting overlap can end up smaller than the source's own
     * metric set.
     *
     * Blocked by default when the target STORE already has any explicitly-saved store-config row
     * (`isBlockedByExistingData=true`, nothing written) unless `$confirmOverwrite` is true — same guard
     * shape as the (store,locale) copy. Fails (`isSuccess=false`) when source and target store are the
     * same.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param string $mode One of {@see MODE_MIRROR}/{@see MODE_COPY_ONLY_OVERLAP}.
     * @param bool $confirmOverwrite
     * @param string $changeSource One of {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig}::CHANGE_SOURCE_* recorded on each copied metric's history row.
     */
    public function copyStoreConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        string $mode,
        bool $confirmOverwrite,
        string $changeSource,
    ): SearchRankingStoreConfigCopyResultTransfer;

    /**
     * True if the given store has any explicitly-saved metric store-config row — the overwrite guard
     * {@see copyStoreConfiguration()} checks, also used by the Zed page to warn before a first submit.
     *
     * @param string $storeName
     */
    public function hasStoreConfiguration(string $storeName): bool;
}
