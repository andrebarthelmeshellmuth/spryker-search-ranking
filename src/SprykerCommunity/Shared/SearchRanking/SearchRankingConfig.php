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
     * - A calibration run just uploaded, queued for the next `search-ranking:calibrate` cron tick.
     *   At most one uploaded row is ever picked up per tick — the newest — the rest move straight to
     *   {@see CALIBRATION_STATUS_SKIPPED}.
     *
     * @api
     *
     * @var string
     */
    public const CALIBRATION_STATUS_UPLOADED = 'uploaded';

    /**
     * Specification:
     * - A superseded upload: a newer one existed by the time the cron ran, so this one was never
     *   calculated.
     *
     * @api
     *
     * @var string
     */
    public const CALIBRATION_STATUS_SKIPPED = 'skipped';

    /**
     * Specification:
     * - The cron has picked this run up and is currently firing search queries for it.
     *
     * @api
     *
     * @var string
     */
    public const CALIBRATION_STATUS_CALCULATING = 'calculating';

    /**
     * Specification:
     * - The run finished: every search term was queried (or skipped on individual failure) and the
     *   pooled score statistics, including {@see CALIBRATION_STATUS_CALCULATED}'s `computedK` suggestion,
     *   are populated.
     *
     * @api
     *
     * @var string
     */
    public const CALIBRATION_STATUS_CALCULATED = 'calculated';

    /**
     * Specification:
     * - The run could not produce any pooled scores at all (e.g. every search term failed or matched
     *   zero products) — `errorMessage` explains why.
     *
     * @api
     *
     * @var string
     */
    public const CALIBRATION_STATUS_FAILED = 'failed';

    /**
     * Specification:
     * - Elasticsearch page-index source identifier passed to `IndexNameResolver::resolve()` when
     *   calibration resolves an index name directly (bypassing Client\Catalog/Client\Search — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\CalibrationSearcher}).
     *
     * @api
     *
     * @var string
     */
    public const PAGE_SOURCE_IDENTIFIER = 'page';
}
