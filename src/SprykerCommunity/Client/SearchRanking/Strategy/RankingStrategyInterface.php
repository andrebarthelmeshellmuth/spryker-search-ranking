<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Strategy;

use Elastica\Query\AbstractQuery;
use Elastica\Query\FunctionScore;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;

/**
 * @internal Phase 3 of "Search Relevance v2" — the seam that turns the one hardcoded ranking pipeline
 * (the adaptive saturating-blend `function_score`) into ONE of several selectable ranking strategies.
 * A strategy owns the decision of HOW a query gets ranked; the
 * {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
 * resolves the active one per query and, when it is body-only, applies it inline.
 *
 * NOT an Extension-namespace plugin interface — this package's own
 * {@see \SprykerCommunity\Client\SearchRanking\SearchRankingFactory::getRankingStrategies()} resolves the
 * active stack from a plain `SearchRankingDependencyProvider` array constant
 * ({@see \SprykerCommunity\Client\SearchRanking\SearchRankingDependencyProvider::PLUGINS_RANKING_STRATEGY}),
 * the same "empty/default array, project extends" pattern already used for
 * {@see \SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface}. It stays refactorable in
 * 1.x — treat it as internal.
 *
 * Shape rationale (load-bearing):
 * - It is deliberately NOT `expand(AbstractQuery, QueryContext): AbstractQuery`. That shape can only ever
 *   express a body-mutating strategy. Some future strategies (a neural rerank second pass, a
 *   `search_pipeline` URL parameter, a `_plugins/*` call) need an execution path the query-expander seam
 *   structurally cannot provide — {@see \Spryker\Client\SearchElasticsearch\Search\Search::executeQuery()}
 *   never forwards `$options`. {@see getExecutionMode()} lets a strategy declare which kind it is; the
 *   expander plugin only auto-applies {@see RankingStrategyExecutionMode::MODE_BODY_ONLY}.
 * - It carries {@see getName()} for IDENTITY only. There is deliberately NO `getParameterSpace()` /
 *   `getTunableParameters()` / any optimizer-vocabulary method here: `search-ranking-optimizer` owns the
 *   parameter space, keyed by strategy name. Keeping its vocabulary out of this interface preserves the
 *   one-way dependency (optimizer -> ranking, never the reverse).
 */
interface RankingStrategyInterface
{
    /**
     * Specification:
     * - Returns a stable, machine-readable identifier for this strategy (e.g. `adaptive_formula`).
     * - Used only for identity: diagnostics/debug output, and as the key `search-ranking-optimizer`
     *   looks its parameter space up under. MUST be stable across releases once published — changing it
     *   silently detaches any optimizer-side parameter space keyed on the old value.
     */
    public function getName(): string;

    /**
     * Specification:
     * - Returns whether this strategy wants to handle the given query context. The active strategy is the
     *   FIRST registered one whose `supports()` returns `true`; the built-in
     *   {@see \SprykerCommunity\Client\SearchRanking\Strategy\AdaptiveFormulaStrategy} returns `true`
     *   unconditionally and is always resolved last, so it is the guaranteed fallback.
     * - Must never throw: a live catalog search must never 500 because strategy selection failed. Any
     *   internal failure degrades to "not supported" (return `false`), the same graceful-degradation
     *   discipline every other best-effort signal in this package follows.
     *
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    public function supports(SearchRankingQueryContextTransfer $queryContextTransfer): bool;

    /**
     * Specification:
     * - Returns one of the {@see RankingStrategyExecutionMode} constants, declaring HOW this strategy must
     *   be executed:
     *   - {@see RankingStrategyExecutionMode::MODE_BODY_ONLY} — the strategy takes full effect by
     *     rewriting the query body; the expander plugin applies it inline via {@see build()}.
     *   - {@see RankingStrategyExecutionMode::MODE_OUT_OF_BAND} — the strategy needs an execution path the
     *     expander seam cannot provide; the expander plugin leaves the query untouched and a dedicated
     *     out-of-band path (v2 Phase 5) runs it.
     */
    public function getExecutionMode(): string;

    /**
     * Specification:
     * - The body-only entry point: given exactly what today's pipeline hands `FunctionScoreBuilder`
     *   (the wrapped query, the resolved configuration transfer, an optional resolved query vector, the
     *   per-query context transfer), returns the `function_score` to replace the query body with, or
     *   `null` to leave the query unchanged (no usable signal terms — same contract as
     *   {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface::build()}).
     * - Only ever called by the expander plugin for a strategy whose {@see getExecutionMode()} is
     *   {@see RankingStrategyExecutionMode::MODE_BODY_ONLY}. An out-of-band strategy is not required to do
     *   anything useful here and may return `null`.
     *
     * @param \Elastica\Query\AbstractQuery $wrappedQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<int, float>|null $queryVector
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer|null $queryContextTransfer
     */
    public function build(
        AbstractQuery $wrappedQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        ?array $queryVector = null,
        ?SearchRankingQueryContextTransfer $queryContextTransfer = null,
    ): ?FunctionScore;
}
