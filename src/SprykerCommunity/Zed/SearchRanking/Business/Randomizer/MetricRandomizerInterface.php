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
     * - Does nothing and returns false when no metric with the configured name (bound at construction)
     *   exists, or it exists but is not active — a deliberate no-op, not an error: whoever schedules this
     *   (typically a nightly cron) should not need to know or care whether the metric happens to be
     *   turned on right now.
     * - Otherwise re-normalizes every product-metric row of that metric (re-evaluating its formula per
     *   row — a formula like `random()` that ignores its `x` input therefore produces fresh values every
     *   call) and republishes every scored product so the new values reach Elasticsearch, then returns
     *   true.
     *
     * @return bool True when the metric was found, active, and refreshed; false on the no-op path.
     */
    public function randomizeIfActive(): bool;
}
