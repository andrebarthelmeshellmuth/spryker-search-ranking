<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;

interface ScopeCopyLockManagerInterface
{
    /**
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array;

    /**
     * Validates the source/target role-exclusivity rule ({@see ScopeCopyLockValidatorInterface}) and,
     * if valid, runs the same overwrite-guarded copy {@see ScopeConfigCopierInterface::copyScopeConfiguration()}
     * does — the lock row is only created once that copy actually succeeds, so a validation failure or an
     * overwrite block never leaves an orphaned lock with no data behind it.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param bool $confirmOverwrite
     */
    public function createScopeCopyLock(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        bool $confirmOverwrite,
    ): SearchRankingScopeCopyResultTransfer;

    /**
     * Deactivates the lock (soft-delete — see the schema column docs on
     * `spy_search_ranking_scope_copy_lock.is_active` for why it's never hard-deleted). Does nothing if
     * the lock doesn't exist or is already inactive.
     *
     * @param int $idSearchRankingScopeCopyLock
     */
    public function deactivateScopeCopyLock(int $idSearchRankingScopeCopyLock): void;

    /**
     * Runs the daily sync: re-copies every active lock's source scope onto its target scope, always
     * overwriting (this is the ongoing authoritative sync, not a first bootstrap — there is no
     * "confirm overwrite" step to gate on for a cron). Intended for the scope-copy-sync console command.
     *
     * @return int Number of locks synced.
     */
    public function runDailySync(): int;
}
