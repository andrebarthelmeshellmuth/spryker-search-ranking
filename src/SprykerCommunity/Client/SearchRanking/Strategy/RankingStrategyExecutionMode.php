<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Strategy;

/**
 * @internal The two ways a {@see \SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyInterface}
 * can be executed. Split exists because a query-expander plugin can only ever mutate the query BODY:
 * {@see \Spryker\Client\SearchElasticsearch\Search\Search::executeQuery()} calls
 * `$index->search($query->getSearchQuery())` and never forwards an `$options` array, so a URL-level
 * parameter (`search_pipeline`) or a `_plugins/*` endpoint can never be reached from the expander seam.
 *
 * Kept as plain string constants (not a native enum) to match this package's existing constant style
 * and to keep the value trivially comparable in the expander plugin and in tests. NOT an
 * Extension-namespace type — same refactor-freely treatment as
 * {@see \SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface}.
 */
final class RankingStrategyExecutionMode
{
    /**
     * The strategy produces its effect purely by rewriting the Elasticsearch query body (today: wrapping
     * it in a `function_score`). Fits the query-expander contract — the
     * {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     * auto-applies strategies of this mode inline, exactly where it used to call `FunctionScoreBuilder`.
     *
     * @var string
     */
    public const MODE_BODY_ONLY = 'body_only';

    /**
     * The strategy needs an execution path outside the single `$index->search()` call the expander seam
     * gets — a second re-ranking pass, an ML inference hop, a `search_pipeline` URL parameter, a
     * `_plugins/*` call. The expander plugin leaves the query untouched for these; a dedicated
     * out-of-band execution path (v2 Phase 5, not yet built) is responsible for running them.
     *
     * @var string
     */
    public const MODE_OUT_OF_BAND = 'out_of_band';
}
