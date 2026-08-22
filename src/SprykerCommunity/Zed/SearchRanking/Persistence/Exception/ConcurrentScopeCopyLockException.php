<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence\Exception;

use RuntimeException;

/**
 * Thrown when creating a scope-copy lock would violate the `active_target_scope_key` unique index -- i.e.
 * an active lock already targets this exact (store, locale) scope. See the schema's own comment on that
 * column for why this is a database-enforced constraint, not just the application-level check
 * ScopeCopyLockValidator already runs (that check alone cannot close the race between two concurrent
 * createScopeCopyLock() calls for the same target).
 */
class ConcurrentScopeCopyLockException extends RuntimeException
{
}
