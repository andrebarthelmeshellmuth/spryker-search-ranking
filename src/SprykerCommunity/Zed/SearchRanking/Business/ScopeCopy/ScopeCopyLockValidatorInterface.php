<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

interface ScopeCopyLockValidatorInterface
{
    /**
     * Enforces, at creation time, that the database can never hold an invalid lock: source and target
     * must differ, a scope may be the target of at most one ACTIVE lock, and a scope can never be
     * simultaneously a source and a target of an ACTIVE lock (in either direction). Checked only against
     * `isActive=true` rows — a point-in-time check, not a lifetime tag, so a fully unlocked scope is free
     * to take on either role again.
     *
     * Returns null when the pair is valid to lock, or a human-readable reason it isn't.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     */
    public function validate(string $sourceStoreName, string $sourceLocaleName, string $targetStoreName, string $targetLocaleName): ?string;
}
