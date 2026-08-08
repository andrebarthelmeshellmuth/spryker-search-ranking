<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business;

use Generated\Shared\Transfer\ProductPageLoadTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigPreviewTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

interface SearchRankingFacadeInterface
{
    /**
     * Specification:
     * - Returns all ranking metric definitions ordered by name, each carrying its weight for the given
     *   store+locale (0.0 if no weight was ever saved for that scope).
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer;

    /**
     * Specification:
     * - Returns all ACTIVE ranking metric definitions ordered by name, with each metric's weight scoped
     *   to the given store and locale.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer;

    /**
     * Specification:
     * - Returns the blend weight for the ranking formula for the given store+locale — the share of the
     *   final score coming from normalized text relevance, with `(1 - relevanceWeight)` going to business
     *   signals; falls back to the module config default when no value was saved yet for that scope.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceWeight(string $storeName, string $localeName): float;

    /**
     * Specification:
     * - Persists the relevance blend-weight setting for the given store+locale.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceWeight
     */
    public function saveRelevanceWeight(string $storeName, string $localeName, float $relevanceWeight): void;

    /**
     * Specification:
     * - Returns the relevance saturation point for the ranking formula for the given store+locale — the
     *   raw Elasticsearch `_score` at which normalized text relevance reaches 0.5; falls back to the
     *   module config default when no value was saved yet for that scope.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceSaturationPoint(string $storeName, string $localeName): float;

    /**
     * Specification:
     * - Persists the relevance saturation-point setting for the given store+locale.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceSaturationPoint
     */
    public function saveRelevanceSaturationPoint(string $storeName, string $localeName, float $relevanceSaturationPoint): void;

    /**
     * Specification:
     * - Returns the blend weight (alpha) combining a query's per-term IDF values into one raw specificity
     *   value, for the given store+locale; falls back to the module config default when no value was
     *   saved yet for that scope. Only meaningful once specificity-aware relevance weighting is enabled
     *   at the code level (a Client-layer bundle config flag, off by default).
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float;

    /**
     * Specification:
     * - Persists the specificity blend-weight setting for the given store+locale.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityBlendWeight
     */
    public function saveSpecificityBlendWeight(string $storeName, string $localeName, float $specificityBlendWeight): void;

    /**
     * Specification:
     * - Returns the raw specificity value at which normalized specificity reaches 0.5, for the given
     *   store+locale; falls back to the module config default when no value was saved yet for that scope.
     *   Calibration-tunable, not tunable by `search-ranking-optimizer`'s own blackbox-optimizer search
     *   (e.g. CMA-ES) — see `relevanceSaturationPoint`'s own docblock for the same split.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificitySaturationPoint(string $storeName, string $localeName): float;

    /**
     * Specification:
     * - Persists the specificity saturation-point setting for the given store+locale.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     * @param float $specificitySaturationPoint
     */
    public function saveSpecificitySaturationPoint(string $storeName, string $localeName, float $specificitySaturationPoint): void;

    /**
     * Specification:
     * - Returns the exponent (`p`) reshaping the raw-to-normalized-specificity curve itself
     *   (`raw^p / (raw^p + k^p)`), for the given store+locale; falls back to the module config default
     *   (`1.0`, the original un-shaped curve) when no value was saved yet for that scope. Tunable by
     *   `search-ranking-optimizer`'s own blackbox-optimizer search (e.g. CMA-ES), same as
     *   `specificityBlendWeight` — unlike `specificitySaturationPoint`, which is Calibration-only.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityCurveExponent(string $storeName, string $localeName): float;

    /**
     * Specification:
     * - Persists the specificity curve-exponent setting for the given store+locale.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityCurveExponent
     */
    public function saveSpecificityCurveExponent(string $storeName, string $localeName, float $specificityCurveExponent): void;

    /**
     * Specification:
     * - Returns the exponent reshaping how sharply the specificity-derived shift ramps up, for the given
     *   store+locale; falls back to the module config default when no value was saved yet for that scope.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightExponent(string $storeName, string $localeName): float;

    /**
     * Specification:
     * - Persists the specificity weight exponent setting for the given store+locale.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightExponent
     */
    public function saveSpecificityWeightExponent(string $storeName, string $localeName, float $specificityWeightExponent): void;

    /**
     * Specification:
     * - Returns the maximum amount the specificity-derived value may shift `relevanceWeight` away from its
     *   configured baseline, in either direction, for the given store+locale; falls back to the module
     *   config default when no value was saved yet for that scope.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float;

    /**
     * Specification:
     * - Persists the specificity weight shift-magnitude setting for the given store+locale.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightShiftMagnitude
     */
    public function saveSpecificityWeightShiftMagnitude(string $storeName, string $localeName, float $specificityWeightShiftMagnitude): void;

    /**
     * Specification:
     * - Divides every ACTIVE metric's weight by the sum of all active weights, and persists the result
     *   into `spy_search_ranking_metric.weight` — the same normalization already forced on every publish
     *   (see `RankingConfigurationStorageWriter`), made visible in the Zed metric list on demand.
     * - Not a correctness requirement — the published/ranking-time weights are already normalized
     *   regardless of whether this was ever called. Purely a transparency action.
     * - Does nothing (no write) when there are no active metrics, all active weights are 0, or the
     *   active weights already sum to 1 — safe to call repeatedly.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     *
     * @return bool True when weights actually changed and were persisted; false when nothing needed to change.
     */
    public function normalizeActiveMetricWeights(string $storeName, string $localeName): bool;

    /**
     * Specification:
     * - Returns the metric with the given id or null if it does not exist, its weight scoped to the given
     *   store and locale.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricById(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Returns the metric with the given name or null if it does not exist.
     *
     * @api
     *
     * @param string $name
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Persists the given metric; creates it when idSearchRankingMetric is empty, updates it otherwise.
     * - Validates the normalization formula and throws InvalidFormulaException when it does not evaluate.
     * - name/isHigherBetter are global; formula/isActive/shape are saved for $storeName specifically.
     *   $localeName is used only as a lens for which digest to fit the saved shape against, never
     *   persisted as part of the scope.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Persists a metric's weight for the given store+locale, and — if it actually changed — records a
     *   history snapshot at that same scope, tagged with $changeSource.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     * @param string $changeSource One of {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig}::CHANGE_SOURCE_*.
     */
    public function saveMetricWeight(
        int $idSearchRankingMetric,
        string $storeName,
        string $localeName,
        float $weight,
        string $changeSource = SharedSearchRankingConfig::CHANGE_SOURCE_MANUAL,
    ): void;

    /**
     * Specification:
     * - Returns every locale name a {@see saveMetricWeight()} call for this metric/store/locale would
     *   actually write to: just $localeName when the metric is locale-scoped (the normal case), or every
     *   real locale of $storeName when it isn't (`isLocaleScoped=false`) — the same fan-out decision
     *   saveMetricWeight() makes internally, exposed up front so a caller can know the real blast radius
     *   of a write before committing it.
     * - A metric that no longer exists resolves to just [$localeName], the same as a locale-scoped one.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<string>
     */
    public function resolveEffectiveWeightLocales(int $idSearchRankingMetric, string $storeName, string $localeName): array;

    /**
     * Specification:
     * - Deletes the metric with the given id; its product-metric rows are removed by cascade.
     * - Does nothing when the metric does not exist.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     */
    public function deleteMetric(int $idSearchRankingMetric): void;

    /**
     * Specification:
     * - Trial-evaluates the given normalization formula against sample variables.
     * - Returns a response transfer with isSuccess and, on failure, the evaluation error message.
     *
     * @api
     *
     * @param string $formula
     */
    public function validateFormula(string $formula): SearchRankingFormulaValidationResponseTransfer;

    /**
     * Specification:
     * - Recalculates the normalized ]0;1] value of every product-metric row of every active metric.
     * - Evaluates the metric's formula per row with variables x (raw value), min, max, avg, count.
     * - Clamps results into ]0;1].
     * - Skips a metric entirely when its formula fails to evaluate and reports it in the result's errors.
     * - `$storeName`/`$localeName` are an optional filter — `null` (the default) fans out over every
     *   store×locale; a real value narrows to just that one scope.
     *
     * @api
     *
     * @param string|null $storeName
     * @param string|null $localeName
     */
    public function normalizeProductMetricValues(?string $storeName = null, ?string $localeName = null): SearchRankingNormalizationResultTransfer;

    /**
     * Specification:
     * - Bulk-loads the normalized ]0;1] values of all active metrics for the products referenced by
     *   `ProductPageLoadTransfer.productAbstractIds`.
     * - Sets them on each payload transfer as a [metricName => normalizedValue] map (empty map when
     *   a product has no scores).
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ProductPageLoadTransfer $productPageLoadTransfer
     */
    public function expandProductPageLoadTransferWithScores(ProductPageLoadTransfer $productPageLoadTransfer): ProductPageLoadTransfer;

    /**
     * Specification:
     * - Triggers `Product.product_abstract.publish` events (in chunks) for every product abstract
     *   that has at least one normalized value of an active metric, so their search documents get
     *   re-exported with fresh scores.
     * - Returns the number of products for which events were triggered.
     *
     * @api
     */
    public function publishScoredProductAbstracts(): int;

    /**
     * Specification:
     * - Recomputes the distribution digest (min/max/mean/median + a 101-point percentile backbone) of
     *   every ACTIVE metric from its current raw_value rows.
     * - A metric with no product-metric rows yet is skipped, not counted.
     * - `$storeName`/`$localeName` are an optional filter — `null` (the default) fans out over every
     *   store×locale; a real value narrows to just that one scope.
     *
     * @api
     *
     * @param string|null $storeName
     * @param string|null $localeName
     *
     * @return int The number of metrics a digest was (re)computed for.
     */
    public function rebuildMetricDigests(?string $storeName = null, ?string $localeName = null): int;

    /**
     * Specification:
     * - Returns the given metric's distribution digest, or null when it has never been computed
     *   (no product-metric rows yet, or {@see rebuildMetricDigests()} has never run).
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricDigest(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricDigestTransfer;

    /**
     * Specification:
     * - Evaluates $formula at every one of the metric's digest percentile x-values and returns the
     *   resulting points, the empirical-CDF reference line, and ranked closed-form curve-fit suggestions
     *   for the given direction ($isHigherBetter).
     * - Sets errorMessage instead of points when the metric has no digest yet, or when $formula fails to
     *   evaluate at any sampled point.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $formula
     * @param bool $isHigherBetter
     * @param string $storeName
     * @param string $localeName
     */
    public function previewFormula(
        int $idSearchRankingMetric,
        string $formula,
        bool $isHigherBetter,
        string $storeName,
        string $localeName,
    ): SearchRankingFormulaPreviewTransfer;

    /**
     * Specification:
     * - Does nothing and returns false when the configured random-tie-breaker metric
     *   ({@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getRandomMetricName()}) does not
     *   exist or is not active.
     * - Otherwise re-normalizes every one of its product-metric rows (producing fresh values, since its
     *   formula is expected to ignore the raw value it's given) and republishes every scored product so
     *   the new values reach Elasticsearch, then returns true.
     * - Intended for a nightly cron, separate from and independent of {@see normalizeProductMetricValues()}'s
     *   hourly cadence, which deliberately skips this same metric.
     * - `$storeName`/`$localeName` are an optional filter — `null` (the default) fans out over every
     *   store×locale; a real value narrows to just that one scope.
     *
     * @api
     *
     * @param string|null $storeName
     * @param string|null $localeName
     */
    public function randomizeRandomMetricIfActive(?string $storeName = null, ?string $localeName = null): bool;

    /**
     * Specification:
     * - Returns the name of the configured random-tie-breaker metric
     *   ({@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getRandomMetricName()}) — the same
     *   identity {@see randomizeRandomMetricIfActive()} and the hourly normalize cron already use to find
     *   and skip this metric. Exposed so other consumers (e.g. an auto-tune settings page) can recognize
     *   and exclude it too, without duplicating the config lookup.
     *
     * @api
     */
    public function getRandomMetricName(): string;

    /**
     * Specification:
     * - Probes the live search engine's ACTUAL capabilities directly — never a version-string comparison,
     *   since OpenSearch and Elasticsearch report incompatible version identifiers under the same API
     *   surface — for a fixed set of constructs this package uses today or could use in a future phase.
     * - Read-only: fires `_validate/query` and a deliberately empty `_rank_eval` request, never writes.
     *
     * @api
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer;

    /**
     * Specification:
     * - Returns whether specificity-aware relevance weighting is active, proxied through the real
     *   {@see \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface::isSpecificityWeightingEnabled()}
     *   (which itself reads the Client-layer `SearchRankingConfig` — the one actually project-overridable
     *   flag; see that class's own docblock) so this Facade reports the SAME, real effective value the
     *   query-building code checks, rather than a second value that would need to be kept in sync by hand.
     * - Unlike every other method on this facade, this is NOT Zed-editable/persisted — it's a pure
     *   code-level project flag, read directly rather than routed through `SettingManager`, since there
     *   is nothing to read-modify-write here.
     *
     * @api
     */
    public function isSpecificityWeightingEnabled(): bool;

    /**
     * Specification:
     * - Appends an `isChange=false` history row for $metricTransfer's CURRENT (unmodified) config and
     *   digest — for a caller (e.g. `spryker-community/search-ranking-optimizer`'s monthly auto-tune job)
     *   that checked a metric's fit but did not change it, so the audit/drift-detection timeline stays
     *   complete even on runs that change nothing.
     * - Never call this after an actual config change — {@see saveMetric()} already records an
     *   `isChange=true` row for that itself.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function recordCheckOnly(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): void;

    /**
     * Specification:
     * - Returns the most recent history row for $idSearchRankingMetric that represents an actual change
     *   (`isChange=true`) — the anchor a drift-detection job compares against, growing the comparison
     *   window on every run that changes nothing rather than resetting it every period.
     * - Returns null when the metric has never had a real change recorded (e.g. brand new).
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     */
    public function findLastMetricChangeHistoryEntry(int $idSearchRankingMetric): ?SearchRankingMetricHistoryTransfer;

    /**
     * Specification:
     * - Evaluates how well $idSearchRankingMetric's OWN CONFIGURED formula fits its digest RIGHT NOW — a
     *   fresh, side-effect-free read (writes nothing), safe to call as often as needed.
     * - Returns null when the metric doesn't exist, or has no digest yet (nothing to fit against).
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function evaluateCurrentMetricFit(int $idSearchRankingMetric, string $storeName, string $localeName): ?float;

    /**
     * Specification:
     * - Same fit check as {@see evaluateCurrentMetricFit()}, run once per real locale of $storeName —
     *   formula/shape are store-only (not locale-scoped) today, so this is the diagnostic that answers
     *   "does this metric's one store-wide formula actually fit every locale's own real data comparably
     *   well" without requiring locale-scoped formulas to exist yet.
     * - Keyed by locale name; a locale with no digest yet maps to null, never omitted or thrown.
     * - Read-only, no side effect, safe to call as often as needed.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     *
     * @return array<string, float|null>
     */
    public function evaluateCurrentMetricFitAcrossLocales(int $idSearchRankingMetric, string $storeName): array;

    /**
     * Specification:
     * - Copies every metric weight and setting explicitly saved for the source scope onto the target
     *   scope, tagged {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY}.
     *   Never touches `spy_search_ranking_product_metric`/`_metric_digest` — real, scope-local behavioral
     *   data is deliberately never copied.
     * - Blocked when the target scope already has any explicitly-saved weight/setting
     *   (`isBlockedByExistingData=true`, nothing written) unless `$confirmOverwrite` is true.
     * - Fails (`isSuccess=false`) when source and target scope are the same.
     * - A one-off action — does not create a lock. See {@see createScopeCopyLock()} for a persistent,
     *   daily-resynced pairing.
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param bool $confirmOverwrite
     */
    public function copyScopeConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        bool $confirmOverwrite,
    ): SearchRankingScopeCopyResultTransfer;

    /**
     * Specification:
     * - True if the given scope has any explicitly-saved metric weight or setting — what
     *   {@see copyScopeConfiguration()}'s overwrite guard checks; exposed so the Zed page can warn
     *   before a first submit too, not only after a blocked one.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function hasScopeConfiguration(string $storeName, string $localeName): bool;

    /**
     * Specification:
     * - Read-only preview of exactly what {@see copyScopeConfiguration()} would act on for the given
     *   source scope: every metric's explicitly-saved weight, and every explicitly-saved setting by its
     *   human-readable label. Never a resolved/defaulted value — same "explicitly saved only" selection
     *   the real copy uses.
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     */
    public function previewScopeConfigurationCopy(string $sourceStoreName, string $sourceLocaleName): SearchRankingScopeCopyPreviewTransfer;

    /**
     * Specification:
     * - Copies formula/isActive/shape for every metric explicitly configured in the source STORE onto
     *   the target store — store-only, not (store,locale)-scoped like {@see copyScopeConfiguration()},
     *   since formula/isActive/shape are themselves store-scoped, not (store,locale)-scoped. Tagged
     *   {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY}.
     * - `MODE_MIRROR` (default) copies every metric the source has configured, creating a target row for
     *   one the target never had. `MODE_COPY_ONLY_OVERLAP` only overwrites a metric the target has
     *   ALREADY independently configured, leaving one it hasn't touched alone (`skippedCount`).
     * - Blocked when the target store already has any explicitly-saved store-config row
     *   (`isBlockedByExistingData=true`, nothing written) unless `$confirmOverwrite` is true.
     * - Fails (`isSuccess=false`) when source and target store are the same.
     * - One-off only — unlike weight/settings, there is no lockable/cron-synced variant of this action,
     *   a deliberate choice: formula/k tuning changes far less often than weight, so a recurring sync
     *   would mostly re-copy an unchanged value.
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param string $mode One of {@see \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface}::MODE_*.
     * @param bool $confirmOverwrite
     */
    public function copyStoreConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        string $mode,
        bool $confirmOverwrite,
    ): SearchRankingStoreConfigCopyResultTransfer;

    /**
     * Specification:
     * - True if the given store has any explicitly-saved metric store-config row — what
     *   {@see copyStoreConfiguration()}'s overwrite guard checks; exposed so the Zed page can warn before
     *   a first submit too, not only after a blocked one.
     *
     * @api
     *
     * @param string $storeName
     */
    public function hasStoreConfiguration(string $storeName): bool;

    /**
     * Specification:
     * - Read-only preview of exactly what {@see copyStoreConfiguration()} would act on for the given
     *   (source store, source locale): every metric's explicitly-saved formula and active flag.
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     */
    public function previewStoreConfigurationSync(string $sourceStoreName, string $sourceLocaleName): SearchRankingStoreConfigPreviewTransfer;

    /**
     * Specification:
     * - Returns every currently active scope copy lock, newest first.
     *
     * @api
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array;

    /**
     * Specification:
     * - Validates the source/target role-exclusivity rule (a scope may be the target of at most one
     *   active lock at a time, source of many, and never simultaneously source and target) — a real
     *   validation failure returns `isSuccess=false` with `errorMessage` set and creates nothing.
     * - If valid, runs the same overwrite-guarded copy {@see copyScopeConfiguration()} does; the lock row
     *   is only created once that copy actually succeeds, so a blocked/failed copy never leaves an
     *   orphaned lock with no data behind it.
     * - On success, the daily scope-copy-sync cron re-runs this same copy for this pair going forward,
     *   until it's unlocked.
     *
     * @api
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param bool $confirmOverwrite
     */
    public function createScopeCopyLock(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        bool $confirmOverwrite,
    ): SearchRankingScopeCopyResultTransfer;

    /**
     * Specification:
     * - Deactivates the lock (soft-delete, never removed — the active-locks page can still show lock
     *   history, and both scopes become free to take on either role again in a future lock).
     * - Does nothing when the lock does not exist or is already inactive.
     *
     * @api
     *
     * @param int $idSearchRankingScopeCopyLock
     */
    public function deactivateScopeCopyLock(int $idSearchRankingScopeCopyLock): void;

    /**
     * Specification:
     * - Re-copies every active lock's source scope onto its target scope, always overwriting (this is
     *   the ongoing authoritative sync, not a first bootstrap — there is no confirmation step to gate a
     *   cron on).
     * - Intended for the scope-copy-sync console command's daily cron.
     *
     * @api
     *
     * @return int Number of locks synced.
     */
    public function runScopeCopyDailySync(): int;
}
