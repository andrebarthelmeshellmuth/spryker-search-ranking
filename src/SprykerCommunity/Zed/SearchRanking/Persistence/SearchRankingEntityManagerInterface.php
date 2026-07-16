<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

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
     * @param string $settingKey
     * @param string $settingValue
     *
     * @return void
     */
    public function saveSetting(string $settingKey, string $settingValue): void;
}
