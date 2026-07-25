<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchRanking;

class SearchRankingConfig
{
    /**
     * Specification:
     * - Registration key of the scores data-expander plugin in the ProductPageSearch plugin stack.
     *
     * @api
     *
     * @var string
     */
    public const PLUGIN_SEARCH_RANKING_SCORES_DATA = 'PLUGIN_SEARCH_RANKING_SCORES_DATA';

    /**
     * Specification:
     * - Name of the Elasticsearch page-document field holding the normalized business signals,
     *   as defined in this package's Schema/page.json. Matches Spryker's data-driven-ranking
     *   best practice (doc['scores.<metric>']).
     *
     * @api
     *
     * @var string
     */
    public const PAGE_INDEX_FIELD_SCORES = 'scores';

    /**
     * Specification:
     * - Setting key of the blend weight used in the function_score script: the share of the final score
     *   that comes from normalized text relevance, with `(1 - relevanceWeight)` going to the weighted
     *   business signals. See {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder}.
     *
     * @api
     *
     * @var string
     */
    public const SETTING_KEY_RELEVANCE_WEIGHT = 'relevance_weight';

    /**
     * Specification:
     * - Setting key of the saturation point used to normalize Elasticsearch's raw, unbounded `_score`
     *   into `]0;1[` before blending: the raw score at which normalized relevance reaches 0.5. See
     *   {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder}.
     *
     * @api
     *
     * @var string
     */
    public const SETTING_KEY_RELEVANCE_SATURATION_POINT = 'relevance_saturation_point';

    /**
     * Specification:
     * - Elasticsearch page-index source identifier passed to `IndexNameResolver::resolve()` when this
     *   package resolves the page index name directly (e.g. from the `search-ranking:check-installation`
     *   console, which runs in Zed where there is no request-scoped "current store").
     *
     * @api
     *
     * @var string
     */
    public const PAGE_SOURCE_IDENTIFIER = 'page';
}
