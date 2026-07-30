<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult;

interface SearchRankingClientInterface
{
    /**
     * Specification:
     * - Probes the live search engine's actual capabilities directly (never a version-string comparison)
     *   for a fixed set of constructs this package uses today or could use in a future phase — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityCheckerInterface} for the
     *   full probe methodology.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer;

    /**
     * Specification:
     * - Whether entropy-aware relevance weighting is active for THIS project, resolved the same
     *   project-override-aware way {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   itself checks before ever firing the live probe query — i.e. via this Client's own, Locator-resolved
     *   `SearchRankingConfig` (overridable in a project's `Pyz\Client\SearchRanking\SearchRankingConfig`),
     *   NOT {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig::isEntropyWeightingEnabled()}
     *   directly — that Shared method is a plain hardcoded `return false;` with no project-override path of
     *   its own; referencing it directly (as this package's own Client config used to, and as
     *   spryker-community/search-ranking-optimizer's `RankEvalRunner` still did before this method existed)
     *   silently ignores any project override.
     * - The one correct way for code OUTSIDE this package (a different package's own evaluation/tooling
     *   logic that reimplements the ranking formula, for instance) to ask whether entropy weighting is
     *   live for this project, without duplicating the Locator-resolution logic itself.
     *
     * @api
     *
     * @return bool
     */
    public function isEntropyWeightingEnabled(): bool;

    /**
     * NOT @api — internal plumbing only. This Client instance is the one object the Locator guarantees
     * stays the SAME single instance across every plugin in this package for the current request, which
     * is exactly what lets {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     * (computes the entropy-adjusted `relevanceWeight` while building the real query) hand its result off
     * to {@see \SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin}
     * (needs that SAME value later, while building the debug overlay) without either plugin knowing about
     * the other. No BC promise; do not call this from project code.
     *
     * Overwritten on every {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin::expandQuery()}
     * call (including with `null`, when entropy weighting is disabled or doesn't apply) — never carries a
     * stale value over from an earlier query in the same request.
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult|null $entropyWeightingResult
     *
     * @return void
     */
    public function rememberLastEntropyWeightingResult(?EntropyWeightingResult $entropyWeightingResult): void;

    /**
     * NOT @api — internal plumbing only, see {@see rememberLastEntropyWeightingResult()}.
     *
     * `null` means entropy weighting did not run for the current query — either it's disabled, or
     * {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin::expandQuery()}
     * hasn't run yet this request.
     *
     * @return \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult|null
     */
    public function getLastEntropyWeightingResult(): ?EntropyWeightingResult;
}
