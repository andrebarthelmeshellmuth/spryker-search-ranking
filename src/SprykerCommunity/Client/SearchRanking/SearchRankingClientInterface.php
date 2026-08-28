<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculatorInterface;

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
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer;

    /**
     * Specification:
     * - Whether specificity-aware relevance weighting is active for THIS project, resolved the same
     *   project-override-aware way {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   itself checks before ever firing the live probe query — i.e. via this Client's own, Locator-resolved
     *   `SearchRankingConfig` (overridable in a project's `Pyz\Client\SearchRanking\SearchRankingConfig`),
     *   NOT {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig::isSpecificityWeightingEnabled()}
     *   directly — that Shared method is a plain hardcoded `return false;` with no project-override path of
     *   its own; referencing it directly silently ignores any project override.
     * - The one correct way for code OUTSIDE this package (a different package's own evaluation/tooling
     *   logic that reimplements the ranking formula, for instance) to ask whether specificity weighting is
     *   live for this project, without duplicating the Locator-resolution logic itself.
     *
     * @api
     */
    public function isSpecificityWeightingEnabled(): bool;

    /**
     * Specification:
     * - The project-override-aware field-to-search-analyzer map specificity probing should use, resolved
     *   the same way {@see isSpecificityWeightingEnabled()} is — via this Client's own, Locator-resolved
     *   `SearchRankingConfig` (overridable in a project's `Pyz\Client\SearchRanking\SearchRankingConfig`).
     * - The one correct way for code OUTSIDE this package (e.g. spryker-community/search-ranking-optimizer's
     *   own evaluation tooling, which reimplements the specificity-probe IO in a Zed/console execution
     *   context this package's own `QueryTermFrequencyFetcher` can't run in) to ask which fields/analyzers
     *   to probe, without duplicating the Locator-resolution logic itself.
     *
     * @api
     *
     * @return array<string, string>
     */
    public function getSpecificityProbeFieldSearchAnalyzers(): array;

    /**
     * Specification:
     * - Returns this package's own function_score query builder — the same one
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   uses to build the real production query.
     * - The one correct way for code OUTSIDE this package (e.g. spryker-community/search-ranking-optimizer's
     *   own evaluation tooling, which re-executes the same ranking formula against live search results) to
     *   get a builder instance, without depending on this package's concrete `Query\FunctionScoreBuilder`
     *   class directly.
     *
     * @api
     */
    public function createFunctionScoreBuilder(): FunctionScoreBuilderInterface;

    /**
     * Specification:
     * - Returns this package's own query-specificity calculator — the same one
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator} uses internally.
     * - The one correct way for code OUTSIDE this package (e.g. spryker-community/search-ranking-optimizer's
     *   own evaluation tooling) to get a calculator instance, without depending on this package's concrete
     *   `Search\QuerySpecificityCalculator` class directly.
     *
     * @api
     */
    public function createQuerySpecificityCalculator(): QuerySpecificityCalculatorInterface;

    /**
     * Specification:
     * - The identity ({@see \SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyInterface::getName()})
     *   of every ranking strategy active in this project — this package's own built-in
     *   {@see \SprykerCommunity\Client\SearchRanking\Strategy\AdaptiveFormulaStrategy} (`adaptive_formula`,
     *   always present) plus whatever a project registered via
     *   {@see \SprykerCommunity\Client\SearchRanking\SearchRankingDependencyProvider::PLUGINS_RANKING_STRATEGY}.
     * - Consumers (e.g. spryker-community/search-ranking-optimizer) use it to refuse operations they have
     *   no support for: strategy selection is per-query (`supports()`), so a consumer that only knows how
     *   to act on `adaptive_formula` asks this "is any OTHER strategy registered?" before touching live
     *   configuration.
     *
     * @api
     *
     * @return array<int, string>
     */
    public function getRegisteredRankingStrategyNames(): array;

    /**
     * NOT @api — internal plumbing only. This Client instance is the one object the Locator guarantees
     * stays the SAME single instance across every plugin in this package for the current request, which
     * is exactly what lets {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     * (builds the per-query {@see \Generated\Shared\Transfer\SearchRankingQueryContextTransfer} — intent
     * signals + the specificity-adjusted `relevanceWeight` — while building the real query) hand its
     * result off to {@see \SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin}
     * (needs that SAME value later, while building the debug overlay) without either plugin knowing about
     * the other. No BC promise; do not call this from project code.
     *
     * Overwritten on every {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin::expandQuery()}
     * call (including with `null`, on the plugin's earliest possible return path) — never carries a stale
     * value over from an earlier query in the same request.
     *
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer|null $queryContextTransfer
     */
    public function rememberQueryContext(?SearchRankingQueryContextTransfer $queryContextTransfer): void;

    /**
     * NOT @api — internal plumbing only, see {@see rememberQueryContext()}.
     *
     * `null` means {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin::expandQuery()}
     * hasn't run yet this request, or returned before building a query context (no search string, no
     * synchronized ranking configuration).
     */
    public function getQueryContext(): ?SearchRankingQueryContextTransfer;

    /**
     * @deprecated Use {@see rememberQueryContext()} instead — this now delegates to it, writing/clearing
     * the nested `specificityWeightingResult` member of the current (or a freshly built empty) query
     * context. Kept only so existing consumers of the old per-signal method pair (this package's own
     * search-debug integration, any project code that called it directly) keep working unchanged.
     *
     * NOT @api — internal plumbing only, see {@see rememberQueryContext()}.
     *
     * @param \Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer|null $specificityWeightingResult
     */
    public function rememberLastSpecificityWeightingResult(?SearchRankingSpecificityWeightingResultTransfer $specificityWeightingResult): void;

    /**
     * @deprecated Use {@see getQueryContext()} instead — this now reads the nested
     * `specificityWeightingResult` member off the current query context.
     *
     * NOT @api — internal plumbing only, see {@see rememberQueryContext()}.
     */
    public function getLastSpecificityWeightingResult(): ?SearchRankingSpecificityWeightingResultTransfer;
}
