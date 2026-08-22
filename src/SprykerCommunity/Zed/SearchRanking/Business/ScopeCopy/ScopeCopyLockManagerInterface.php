<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingFullScopeCopyResultTransfer;

interface ScopeCopyLockManagerInterface
{
    /**
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array;

    /**
     * Validates the source/target role-exclusivity rule ({@see ScopeCopyLockValidatorInterface}) and, if
     * valid, runs the same overwrite-guarded combined copy {@see FullScopeCopierInterface::copyFullScopeConfiguration()}
     * does (always `MODE_MIRROR` — a lock makes the target a full, ongoing mirror of the source) — the
     * lock row is only created once that copy actually succeeds, so a validation failure or an overwrite
     * block never leaves an orphaned lock with no data behind it. Only the recurring daily resync
     * ({@see runDailySync()}) stays weight/setting-only.
     *
     * The validator's own check cannot fully close the race between two concurrent calls locking the SAME
     * target scope — a database unique index on the lock row's active target scope is the real backstop
     * (see `SearchRankingEntityManager::createScopeCopyLock()`); losing that race comes back as a normal
     * `isSuccess=false` result here, same shape as any other validation failure, never an uncaught
     * exception.
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
    ): SearchRankingFullScopeCopyResultTransfer;

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
     * "confirm overwrite" step to gate on for a cron). Weight/setting only, always `MODE_MIRROR` — unlike
     * {@see createScopeCopyLock()}'s one-time bootstrap, this never touches formula/isActive/shape again
     * (see that method's own docblock for why). Intended for the scope-copy-sync console command.
     *
     * @return int Number of locks synced.
     */
    public function runDailySync(): int;
}
