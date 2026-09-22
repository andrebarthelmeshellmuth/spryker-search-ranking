<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer;

interface SearchRankingRepositoryInterface
{
    /**
     * Every metric, with its (store, locale)-scoped formula/isActive/shape overlaid — weight deliberately
     * NOT attached here (a separate, genuinely independent scoped value). A caller that also needs weight
     * calls {@see attachWeights()} on the result.
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer;

    /**
     * Same weight-free contract as {@see getMetricCollection()}, filtered to metrics this (store, locale)'s
     * own store-config marks active.
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getActiveMetricCollection(string $storeName, string $localeName): SearchRankingMetricCollectionTransfer;

    /**
     * Overlays $storeName/$localeName's real per-metric weight onto every metric already in
     * $collectionTransfer (from {@see getMetricCollection()}/{@see getActiveMetricCollection()}),
     * mutating and returning the same collection instance. A separate step from building the collection
     * itself so a caller that doesn't need weight never pays for (or has to fake a locale to trigger) the
     * per-metric weight lookup this performs.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer $collectionTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function attachWeights(
        SearchRankingMetricCollectionTransfer $collectionTransfer,
        string $storeName,
        string $localeName,
    ): SearchRankingMetricCollectionTransfer;

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricById(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricTransfer;

    /**
     * @param string $name
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricByName(string $name, string $storeName, string $localeName): ?SearchRankingMetricTransfer;

    /**
     * Null when no weight row exists yet for this (metric, store, locale) — callers treat that the same
     * as an explicit weight of 0.0 (an inactive-equivalent contribution), never as an error.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName): ?float;

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function getMetricStatistics(int $idSearchRankingMetric, string $storeName, string $localeName): SearchRankingMetricStatisticsTransfer;

    /**
     * Result rows are ordered by id ascending; pass the last seen id to page through the full set.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param int $idLastSearchRankingProductMetric
     * @param int $limit
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingProductMetricTransfer>
     */
    public function getProductMetricBatch(
        int $idSearchRankingMetric,
        string $storeName,
        string $localeName,
        int $idLastSearchRankingProductMetric,
        int $limit,
    ): array;

    /**
     * Returns normalized values of ACTIVE metrics keyed by product abstract id, then metric name:
     * [idProductAbstract => [metricName => normalizedValue]]. Rows without a normalized value
     * (cron not run yet) are omitted.
     *
     * @param array<int> $productAbstractIds
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<int, array<string, float>>
     */
    public function getNormalizedScoresGroupedByIdProductAbstract(array $productAbstractIds, string $storeName, string $localeName): array;

    /**
     * Returns the distinct product abstract ids that have at least one normalized value of an
     * active metric, in ANY store/locale — i.e. the products whose search documents carry scores
     * somewhere. Deliberately not scope-filtered: a product abstract publish event fans out into one
     * search document per store×locale downstream regardless, so this only needs to know WHICH
     * abstracts are affected at all, not in which scope specifically.
     *
     * @return array<int>
     */
    public function getProductAbstractIdsWithActiveMetricValues(): array;

    /**
     * @param string $settingKey
     * @param string $storeName
     * @param string $localeName
     */
    public function findSettingValue(string $settingKey, string $storeName, string $localeName): ?string;

    /**
     * Every raw_value of the metric's product-metric rows for this store+locale, as a lean single-column
     * projection (not hydrated into full transfers) — {@see \SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilder}
     * sorts and walks this in PHP rather than relying on DB-engine percentile functions, since this
     * package supports both MySQL and PostgreSQL and MySQL has no portable PERCENTILE_CONT.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<float>
     */
    public function getRawValues(int $idSearchRankingMetric, string $storeName, string $localeName): array;

    /**
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricDigest(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricDigestTransfer;

    /**
     * Every history snapshot of the given metric for this store+locale, newest first.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer>
     */
    public function getMetricHistory(int $idSearchRankingMetric, string $storeName, string $localeName): array;

    /**
     * The most recent history row for this store+locale that represents an actual change (`isChange`
     * true) — the anchor a drift-detection read should compare the metric's CURRENT state against,
     * rather than always the most recent snapshot taken. This is what makes the comparison window grow
     * (30 days, then 60, then 90, ...) for as long as a metric's formula is left untouched, instead of
     * resetting to "vs. last month" on every periodic check regardless of whether anything actually
     * changed.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findLastMetricChangeHistoryEntry(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricHistoryTransfer;

    /**
     * Every currently active lock, newest first — the full set a lock-creation validation scans for the
     * source/target role-exclusivity check, and what the Zed page's active-locks table renders. The set
     * is expected to stay small (one row per market expansion in flight), so callers scan it in PHP
     * rather than this repository offering per-scope filtered variants.
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array;

    /**
     * Every lock ever created, active or not, newest first — the append-only episode log backing the
     * Zed page's lock-history view.
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getAllScopeCopyLocks(): array;

    /**
     * @param int $idSearchRankingScopeCopyLock
     */
    public function findScopeCopyLockById(int $idSearchRankingScopeCopyLock): ?SearchRankingScopeCopyLockTransfer;

    /**
     * The (store, locale)-scoped `formula`/`isActive`/`shape` row for one metric — used by
     * {@see \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopier} to check whether
     * the target already has an explicit config for a given metric before copying/overwriting.
     * Null when no row exists yet for this (metric, store, locale).
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function findMetricStoreConfig(int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricStoreConfigTransfer;

    /**
     * True if the scope has any explicitly-saved `spy_search_ranking_metric_weight` or
     * `spy_search_ranking_setting` row — deliberately NOT based on {@see getMetricCollection()}/the
     * setting getters, which always return a value (falling back to 0.0/a config default), so they
     * can't distinguish "explicitly saved" from "never touched, using the default".
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function hasScopeConfiguration(string $storeName, string $localeName): bool;

    /**
     * True if the store has any explicitly-saved `spy_search_ranking_metric_store_config` row —
     * store-only counterpart to {@see hasScopeConfiguration()}, which checks the (store,locale)-scoped
     * weight/setting tables instead. Deliberately NOT based on {@see getMetricCollection()}, which always
     * returns a value for every metric (falling back to isActive=false/formula=null via the safe-absence
     * overlay), so it can't distinguish "explicitly configured" from "never touched".
     *
     * @param string $storeName
     */
    public function hasStoreConfiguration(string $storeName): bool;

    /**
     * Bulk-loads stored semantic embedding vectors for the given product abstracts, for one
     * (store, locale, modelId) scope — the N+1-avoiding counterpart
     * {@see \SprykerCommunity\Zed\SearchRanking\Business\PageData\EmbeddingPageDataLoader} needs during a
     * full-catalog publish. Products with no stored vector for this scope are simply absent from the
     * result, same "absence, not a zero value" convention
     * {@see getNormalizedScoresGroupedByIdProductAbstract()} uses for scores.
     *
     * @param array<int> $productAbstractIds
     * @param string $storeName
     * @param string $localeName
     * @param string $modelId
     *
     * @return array<int, array<int, float>>
     */
    public function getEmbeddingsGroupedByIdProductAbstract(
        array $productAbstractIds,
        string $storeName,
        string $localeName,
        string $modelId,
    ): array;

    /**
     * The stored `text_hash` for one product's embedding row, if one exists for this exact
     * (store, locale, modelId) scope — lets the offline generation job skip re-embedding a product whose
     * name/description text hasn't changed since the last run. Null if no row exists yet.
     *
     * @param int $idProductAbstract
     * @param string $storeName
     * @param string $localeName
     * @param string $modelId
     */
    public function findEmbeddingTextHash(
        int $idProductAbstract,
        string $storeName,
        string $localeName,
        string $modelId,
    ): ?string;
}
