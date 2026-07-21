<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;

interface SearchRankingEntityManagerInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer;

    /**
     * @param int $idSearchRankingMetric
     *
     * @return void
     */
    public function deleteMetric(int $idSearchRankingMetric): void;

    /**
     * @param array<int, float> $normalizedValuesByIdProductMetric
     *
     * @return void
     */
    public function updateNormalizedValues(array $normalizedValuesByIdProductMetric): void;

    /**
     * @param array<int, float> $weightsByIdSearchRankingMetric
     *
     * @return void
     */
    public function updateMetricWeights(array $weightsByIdSearchRankingMetric): void;

    /**
     * @param string $settingKey
     * @param string $settingValue
     *
     * @return void
     */
    public function saveSetting(string $settingKey, string $settingValue): void;

    /**
     * Creates a calibration run in status=uploaded together with one child row per
     * `$calibrationTransfer->getSearchTerms()` entry (search term text only, no scores yet).
     *
     * @param \Generated\Shared\Transfer\SearchRankingCalibrationTransfer $calibrationTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer
     */
    public function createCalibration(SearchRankingCalibrationTransfer $calibrationTransfer): SearchRankingCalibrationTransfer;

    /**
     * @param int $idSearchRankingCalibration
     * @param string $status
     *
     * @return void
     */
    public function updateCalibrationStatus(int $idSearchRankingCalibration, string $status): void;

    /**
     * @param int $idSearchRankingCalibrationSearchTerm
     * @param int $productsFound
     * @param array<float> $scores
     *
     * @return void
     */
    public function saveCalibrationSearchTermResult(int $idSearchRankingCalibrationSearchTerm, int $productsFound, array $scores): void;

    /**
     * Persists the pooled score statistics and sets status=calculated, calculatedAt=now.
     *
     * @param int $idSearchRankingCalibration
     * @param \Generated\Shared\Transfer\SearchRankingCalibrationTransfer $statisticsTransfer
     *
     * @return void
     */
    public function saveCalibrationStatistics(int $idSearchRankingCalibration, SearchRankingCalibrationTransfer $statisticsTransfer): void;

    /**
     * Sets status=failed and errorMessage.
     *
     * @param int $idSearchRankingCalibration
     * @param string $errorMessage
     *
     * @return void
     */
    public function markCalibrationFailed(int $idSearchRankingCalibration, string $errorMessage): void;

    /**
     * Upserts by `fkSearchRankingMetric` — one digest row per metric, overwritten wholesale on every
     * rebuild rather than versioned, since only the CURRENT distribution is ever meaningful.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer
     */
    public function saveMetricDigest(SearchRankingMetricDigestTransfer $digestTransfer): SearchRankingMetricDigestTransfer;
}
