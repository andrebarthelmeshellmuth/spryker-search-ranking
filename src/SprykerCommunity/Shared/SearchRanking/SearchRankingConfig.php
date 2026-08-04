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
     * - Setting key of the blend weight (alpha) used to combine a query's per-term IDF values into one
     *   "raw specificity" value: `alpha * max(idf) + (1 - alpha) * harmonicMean(idf)`. CMA-ES-tunable via
     *   spryker-community/search-ranking-optimizer. Only meaningful when specificity-aware relevance
     *   weighting is enabled — see {@see isSpecificityWeightingEnabled()}.
     *
     * @api
     *
     * @var string
     */
    public const SETTING_KEY_SPECIFICITY_BLEND_WEIGHT = 'specificity_blend_weight';

    /**
     * Specification:
     * - Setting key of the saturation point (k) used to normalize the unbounded raw specificity value
     *   into `[0;1[` before deriving the shift: the raw specificity at which the normalized result
     *   reaches 0.5. Calibration-tunable only (like `relevance_saturation_point`), NOT CMA-ES-tunable.
     *
     * @api
     *
     * @var string
     */
    public const SETTING_KEY_SPECIFICITY_SATURATION_POINT = 'specificity_saturation_point';

    /**
     * Specification:
     * - Setting key of the exponent that reshapes how sharply the specificity-derived shift ramps up as
     *   normalized specificity moves away from perfectly average (0.5).
     *
     * @api
     *
     * @var string
     */
    public const SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT = 'specificity_weight_exponent';

    /**
     * Specification:
     * - Setting key of the maximum amount the specificity-derived value may shift `relevanceWeight` away
     *   from its configured baseline, in either direction.
     *
     * @api
     *
     * @var string
     */
    public const SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE = 'specificity_weight_shift_magnitude';

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

    /**
     * Specification:
     * - TEMPORARY Phase-1 safety-valve default store, used by every caller of a now-store+locale-scoped
     *   read/write until later phases thread a real caller-supplied (admin-selected, request-current, or
     *   run/calibration-carried) store+locale through instead. Not meant to be read directly by project
     *   code — exists purely so this package's own internals keep behaving exactly as they did when
     *   configuration was global, for a shop that (like this demoshop) only ever configures one store.
     *
     * @api
     *
     * @var string
     */
    public const DEFAULT_SCOPE_STORE_NAME = 'DE';

    /**
     * Specification:
     * - TEMPORARY Phase-1 safety-valve default locale — see {@see DEFAULT_SCOPE_STORE_NAME}.
     *
     * @api
     *
     * @var string
     */
    public const DEFAULT_SCOPE_LOCALE_NAME = 'de_DE';

    /**
     * Specification:
     * - Whether specificity-aware relevance weighting is active. OFF by default — enabling it makes
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   fire ONE ADDITIONAL lightweight `_termvectors` probe per live catalog search to derive a per-query
     *   `relevanceWeight` instead of using the configured static one.
     * - **This class is a plain static-method holder, not a project-overridable `AbstractSharedConfig`** —
     *   there is no real way to make THIS method return `true` from a project. Do not override a
     *   `Pyz\Shared\SearchRanking\SearchRankingConfig` expecting it to take effect here; it won't. The one
     *   override point that actually works is `Pyz\Client\SearchRanking\SearchRankingConfig::isSpecificityWeightingEnabled()`
     *   — that class IS a real, Locator-resolved `AbstractBundleConfig`, which is what
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   actually checks (via `$this->getFactory()->getConfig()`), and what {@see \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface::isSpecificityWeightingEnabled()}
     *   exposes for other code to ask without duplicating that resolution logic. This method's `false`
     *   return only matters as the Client config's own internal default when a project hasn't overridden it.
     * - Deliberately a code-level flag, not a Zed-editable setting: this is the one switch that decides
     *   whether a second live probe fires on every catalog search at all, so flipping it requires a
     *   project deploy, not just a Zed form save. The probe's own tuning numbers (blend weight, weight
     *   exponent, shift magnitude) ARE Zed-editable — see `/search-ranking-gui/settings` — once this flag
     *   is on; the saturation point is Calibration-tunable only.
     *
     * @api
     */
    public static function isSpecificityWeightingEnabled(): bool
    {
        return false;
    }
}
