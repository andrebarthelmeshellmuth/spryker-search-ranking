<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Randomizer;

interface MetricRandomizerInterface
{
    /**
     * Specification:
     * - Iterates every store×locale; a scope is skipped (a deliberate no-op, not an error) when no metric
     *   with the configured name (bound at construction) exists for it, or it exists but is not active —
     *   whoever schedules this (typically a nightly cron) should not need to know or care whether the
     *   metric happens to be turned on for every store right now.
     * - For every scope where it IS active, re-normalizes every product-metric row of that metric
     *   (re-evaluating its formula per row — a formula like `random()` that ignores its `x` input
     *   therefore produces fresh values every call). Republishes every scored product exactly once, after
     *   all scopes have been re-normalized, so the new values reach Elasticsearch.
     * - `$storeName`/`$localeName` are an optional filter — `null` (the default) fans out over every
     *   store×locale; a real value narrows to just that one scope.
     *
     * @param string|null $storeName
     * @param string|null $localeName
     *
     * @return bool True when at least one scope was found, active, and refreshed; false when every scope
     * was a no-op.
     */
    public function randomizeIfActive(?string $storeName = null, ?string $localeName = null): bool;
}
