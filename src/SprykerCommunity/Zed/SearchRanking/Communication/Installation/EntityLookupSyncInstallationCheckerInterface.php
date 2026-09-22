<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Installation;

use Generated\Shared\Transfer\SearchRankingEntityLookupSyncDiagnosisTransfer;

interface EntityLookupSyncInstallationCheckerInterface
{
    /**
     * Specification:
     * - Reports the raw facts behind Pass 2's entity-lookup sync installation state: whether cron mode is
     *   declared/wired (project config flag, or real introspection of a resolved scheduler config) and
     *   whether the near-live event-hook plugin is registered (reflection into the resolved
     *   `Pyz\Zed\ProductPageSearch\ProductPageSearchDependencyProvider::getDataLoaderPlugins()` plugin stack).
     * - `eventHookRegistrationUnknown` is `true` when that reflection could not be performed at all
     *   (`spryker/product-page-search` absent, or the resolved provider couldn't be reflected into) —
     *   deliberately distinct from a confirmed-false `eventHookRegistered`, so a caller can tell
     *   "cannot tell" apart from "no". `eventHookRegistered` is `false` in that case too, purely so callers
     *   that only care about the collapsed signal do not also have to null-check it.
     * - Deciding what a given combination of these two facts MEANS (pass/notice/error, and the exact
     *   wording) is deliberately left to the caller — see
     *   {@see \SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole::checkEntityLookupSyncConfiguration()},
     *   the only consumer today.
     * - Never throws.
     */
    public function check(): SearchRankingEntityLookupSyncDiagnosisTransfer;
}
