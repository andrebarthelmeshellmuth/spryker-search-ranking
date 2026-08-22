<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer;
use Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer;

interface SearchRankingEntityManagerInterface
{
    /**
     * Writes the metric's global identity (name/isHigherBetter), then its formula/isActive/shape
     * separately to `spy_search_ranking_metric_store_config` for exactly (`$storeName`, `$localeName`) —
     * no fan-out to sibling locales at this layer; see {@see \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface::saveMetric()}
     * for where that decision (governed by the metric's own `isLocaleScoped`) is actually made.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): SearchRankingMetricTransfer;

    /**
     * @param int $idSearchRankingMetric
     */
    public function deleteMetric(int $idSearchRankingMetric): void;

    /**
     * @param array<int, float> $normalizedValuesByIdProductMetric
     */
    public function updateNormalizedValues(array $normalizedValuesByIdProductMetric): void;

    /**
     * Upserts one metric's weight for one (store, locale) — deliberately bypasses `saveMetric()`'s
     * mandatory formula re-validation, which is unnecessary work and an unnecessary failure surface here
     * since the formula itself never changes. Same "narrow, single-field write" shape as
     * {@see updateNormalizedValues()}.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     */
    public function saveMetricWeight(int $idSearchRankingMetric, string $storeName, string $localeName, float $weight): void;

    /**
     * @param array<int, float> $weightsByIdSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     */
    public function updateMetricWeights(array $weightsByIdSearchRankingMetric, string $storeName, string $localeName): void;

    /**
     * @param string $settingKey
     * @param string $storeName
     * @param string $localeName
     * @param string $settingValue
     */
    public function saveSetting(string $settingKey, string $storeName, string $localeName, string $settingValue): void;

    /**
     * Always inserts a new row — history is append-only, never updated or upserted.
     *
     * @param \Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer $settingHistoryTransfer
     */
    public function recordSettingHistory(SearchRankingSettingHistoryTransfer $settingHistoryTransfer): SearchRankingSettingHistoryTransfer;

    /**
     * Upserts by `(fkSearchRankingMetric, storeName, localeName)` — one digest row per metric per scope,
     * overwritten wholesale on every rebuild rather than versioned, since only the CURRENT distribution
     * is ever meaningful.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     */
    public function saveMetricDigest(SearchRankingMetricDigestTransfer $digestTransfer): SearchRankingMetricDigestTransfer;

    /**
     * Always inserts a new row — history is append-only, never updated or upserted.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer $historyTransfer
     */
    public function recordMetricHistory(SearchRankingMetricHistoryTransfer $historyTransfer): SearchRankingMetricHistoryTransfer;

    /**
     * Always inserts a new row — lock episodes are append-only, never updated or upserted; unlocking
     * a pair later deactivates that same row rather than creating a new one, but relocking after that
     * creates a fresh row, it never reactivates the old one.
     *
     * @param \Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer $scopeCopyLockTransfer
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Persistence\Exception\ConcurrentScopeCopyLockException
     */
    public function createScopeCopyLock(SearchRankingScopeCopyLockTransfer $scopeCopyLockTransfer): SearchRankingScopeCopyLockTransfer;

    /**
     * Sets isActive to false and deactivatedAt to now. Does nothing (no error) if the row doesn't exist
     * or is already inactive — safe to call repeatedly.
     *
     * @param int $idSearchRankingScopeCopyLock
     */
    public function deactivateScopeCopyLock(int $idSearchRankingScopeCopyLock): void;

    /**
     * Upserts by (fkSearchRankingMetric, storeName, localeName) — one store-config row per metric per
     * (store, locale), overwritten wholesale rather than versioned (mirrors {@see saveMetricDigest()}'s
     * own upsert shape). Called once per real locale of the target store when the metric is
     * `isLocaleScoped=false` (fanned out), or once for just the one named locale when it's `true` — the
     * fan-out decision itself lives in {@see \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface::saveMetric()},
     * not here.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer $metricStoreConfigTransfer
     */
    public function saveMetricStoreConfig(SearchRankingMetricStoreConfigTransfer $metricStoreConfigTransfer): SearchRankingMetricStoreConfigTransfer;
}
