<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingFullScopeCopyPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFullScopeCopyResultTransfer;

/**
 * The Scope Copy page's single combined "Copy now"/"Lock" action, wrapping {@see ScopeConfigCopierInterface}
 * (metric weight + the 6 tunable settings, genuinely store+locale scoped) and {@see StoreConfigCopierInterface}
 * (formula/isActive/shape) behind one call — both are governed by the same per-metric `isLocaleScoped` fact
 * since the "Unify isLocaleScoped" migration, so there is no remaining reason for an admin to drive them
 * through two independent pickers.
 */
interface FullScopeCopierInterface
{
    /**
     * Runs {@see ScopeConfigCopierInterface::copyScopeConfiguration()} and, unless the source and target
     * are the SAME store (only the locale differs), also {@see StoreConfigCopierInterface::copyStoreConfiguration()}
     * — that class's own guard fails outright on a same-store source/target, since for an
     * `isLocaleScoped=false` metric (most metrics) the write already fans out to every locale of the
     * store, and for an `isLocaleScoped=true` one, syncing a store's formula onto itself across locales
     * isn't a supported scenario yet. In that case storeConfigCopiedCount/storeConfigSkippedCount are
     * always 0 rather than the whole combined action failing — the weight/setting half still runs.
     *
     * Blocked by default (`isBlockedByExistingData=true`, nothing written at all) unless $confirmOverwrite
     * is true — checked via {@see hasFullScopeConfiguration()} BEFORE either half writes anything, so a
     * blocked combined copy never leaves a torn write behind (only the weight/setting half succeeding
     * while the store-config half silently fails, or vice versa).
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param string $mode One of {@see ScopeConfigCopierInterface::MODE_MIRROR}/{@see ScopeConfigCopierInterface::MODE_COPY_ONLY_OVERLAP} — applied to both halves identically.
     * @param bool $confirmOverwrite
     * @param string $changeSource One of {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig}::CHANGE_SOURCE_* recorded on each copied row's history.
     */
    public function copyFullScopeConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        string $mode,
        bool $confirmOverwrite,
        string $changeSource,
    ): SearchRankingFullScopeCopyResultTransfer;

    /**
     * True if either half of the combined copy would collide with data the target already independently
     * has — {@see ScopeConfigCopierInterface::hasBlockingExistingData()} for weight/setting, plus
     * {@see StoreConfigCopierInterface::hasStoreConfiguration()} for the target STORE's formula/isActive/shape
     * (skipped when source and target are the same store, matching {@see copyFullScopeConfiguration()}'s
     * own same-store skip). Used both as the real pre-flight guard and by the Zed page to warn before a
     * first submit.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     */
    public function hasFullScopeConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
    ): bool;

    /**
     * Read-only preview of exactly what {@see copyFullScopeConfiguration()} would act on for the given
     * source scope — combines {@see ScopeConfigCopierInterface::previewScopeConfiguration()} and
     * {@see StoreConfigCopierInterface::previewStoreConfiguration()}, same "explicitly saved only"
     * selection both already use.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     */
    public function previewFullScopeConfiguration(string $sourceStoreName, string $sourceLocaleName): SearchRankingFullScopeCopyPreviewTransfer;
}
