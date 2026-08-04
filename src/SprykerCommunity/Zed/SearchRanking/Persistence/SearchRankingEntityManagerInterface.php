<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer;

interface SearchRankingEntityManagerInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer;

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
}
