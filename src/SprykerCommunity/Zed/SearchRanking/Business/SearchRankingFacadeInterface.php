<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business;

use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;

interface SearchRankingFacadeInterface
{
    /**
     * Specification:
     * - Returns all ranking metric definitions ordered by name.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer
     */
    public function getMetricCollection(): SearchRankingMetricCollectionTransfer;

    /**
     * Specification:
     * - Returns the metric with the given id or null if it does not exist.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricById(int $idSearchRankingMetric): ?SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Returns the metric with the given name or null if it does not exist.
     *
     * @api
     *
     * @param string $name
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function findMetricByName(string $name): ?SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Persists the given metric; creates it when idSearchRankingMetric is empty, updates it otherwise.
     * - Validates the normalization formula and throws InvalidFormulaException when it does not evaluate.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer;

    /**
     * Specification:
     * - Deletes the metric with the given id; its product-metric rows are removed by cascade.
     * - Does nothing when the metric does not exist.
     *
     * @api
     *
     * @param int $idSearchRankingMetric
     *
     * @return void
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
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer
     */
    public function validateFormula(string $formula): SearchRankingFormulaValidationResponseTransfer;

    /**
     * Specification:
     * - Recalculates the normalized ]0;1] value of every product-metric row of every active metric.
     * - Evaluates the metric's formula per row with variables x (raw value), min, max, avg, count.
     * - Clamps results into ]0;1].
     * - Skips a metric entirely when its formula fails to evaluate and reports it in the result's errors.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer
     */
    public function normalizeProductMetricValues(): SearchRankingNormalizationResultTransfer;
}
