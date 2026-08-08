<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingStoreConfigCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigPreviewTransfer;

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
     * for every metric EXPLICITLY configured at (source store, source locale) onto (target store, target
     * locale). For an `isLocaleScoped=false` metric (most metrics — see
     * {@see \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface}'s own docs
     * on the flag) this is effectively still store-only in outcome:
     * {@see \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface::saveMetric()} fans
     * the write out to every real locale of the target store regardless of which one locale was named
     * here, the same fan-out {@see ScopeConfigCopierInterface::copyScopeConfiguration()}'s weight copy
     * already relies on. Only for an `isLocaleScoped=true` metric does the write actually stay scoped to
     * just the one (target store, target locale) pair named here. The one real caller
     * ({@see \SprykerCommunity\Zed\SearchRankingGui\Communication\Controller\StoreConfigSyncRunController})
     * has its own independent source/target locale pickers for exactly this case.
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

    /**
     * Read-only preview of exactly what {@see copyStoreConfiguration()} would act on for the given
     * (source store, source locale) — same "explicitly saved only" selection.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     */
    public function previewStoreConfiguration(string $sourceStoreName, string $sourceLocaleName): SearchRankingStoreConfigPreviewTransfer;
}
