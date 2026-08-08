<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingScopeCopyPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;

interface ScopeConfigCopierInterface
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
     * Copies every metric weight and the 6 tunable settings that were EXPLICITLY saved for the source
     * scope onto the target scope — never `spy_search_ranking_product_metric`/`_metric_digest`, which
     * stay scope-local real behavioral data and are deliberately never copied; a freshly bootstrapped
     * market should still show honest gaps on its own Product Metric Gaps page.
     *
     * `MODE_MIRROR` (default): copies every weight/setting the source has explicitly saved, creating a
     * new target row for one the target has never configured at all — the original bootstrap philosophy.
     * `MODE_COPY_ONLY_OVERLAP`: conservative, opt-in — only overwrites a weight/setting the TARGET has
     * already independently configured; one the target has never touched is left alone (counted in
     * `skippedCount`, not an error). Same semantics as {@see \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface}'s
     * modes, so an admin refreshing an established scope from a re-tuned source doesn't accidentally wake
     * up metrics/settings that scope deliberately never adopted.
     *
     * Blocked by default (`isBlockedByExistingData=true`, nothing written) unless `$confirmOverwrite` is
     * true — the same guard applies whether this is a one-off "Copy now" or a lock's immediate copy. The
     * target scope itself already having any explicitly-saved weight or setting is one trigger; a
     * not-locale-scoped metric being copied that would fan out onto a SIBLING locale of the target store
     * which already has its own weight is the other — both are real existing-data collisions, even though
     * only the first one is visible by looking at the target scope alone.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param string $mode One of {@see MODE_MIRROR}/{@see MODE_COPY_ONLY_OVERLAP}.
     * @param bool $confirmOverwrite
     * @param string $changeSource One of {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig}::CHANGE_SOURCE_* recorded on each copied metric weight's history row.
     */
    public function copyScopeConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        string $mode,
        bool $confirmOverwrite,
        string $changeSource,
    ): SearchRankingScopeCopyResultTransfer;

    /**
     * True if the given scope has any explicitly-saved metric weight or setting — the overwrite guard
     * {@see copyScopeConfiguration()} checks, also used by the Zed page to warn before a first submit.
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function hasScopeConfiguration(string $storeName, string $localeName): bool;

    /**
     * The real, richer guard {@see copyScopeConfiguration()} uses before writing — {@see hasScopeConfiguration()}'s
     * direct-target check PLUS the sibling-locale fan-out collision check (see this method's own
     * implementation docblock). Exposed publicly so a caller combining this copy with another one (see
     * {@see \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\FullScopeCopierInterface}) can pre-flight
     * both halves before writing either.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     */
    public function hasBlockingExistingData(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
    ): bool;

    /**
     * Read-only preview of exactly what {@see copyScopeConfiguration()} would act on for the given
     * source scope — same "explicitly saved only" selection, never a resolved/defaulted value.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     */
    public function previewScopeConfiguration(string $sourceStoreName, string $sourceLocaleName): SearchRankingScopeCopyPreviewTransfer;
}
