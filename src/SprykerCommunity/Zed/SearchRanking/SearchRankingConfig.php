<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking;

use Spryker\Zed\Kernel\AbstractBundleConfig;

class SearchRankingConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Number of product-metric rows the normalization processes per batch.
     *
     * @api
     *
     * @return int
     */
    public function getNormalizationBatchSize(): int
    {
        return 1000;
    }

    /**
     * Specification:
     * - Lower clamp bound for normalized values. Normalized values must lie in ]0;1], so results
     *   of 0 or below are raised to this minimum, results above 1 are capped at 1.
     *
     * @api
     *
     * @return float
     */
    public function getNormalizedValueMinimum(): float
    {
        return 1.0E-6;
    }

    /**
     * Specification:
     * - Number of product abstract publish events triggered per bulk when re-publishing scored products.
     *
     * @api
     *
     * @return int
     */
    public function getPublishEventChunkSize(): int
    {
        return 500;
    }

    /**
     * Specification:
     * - Sample variables used to trial-evaluate a formula during validation.
     *
     * @api
     *
     * @return array<string, float|int>
     */
    public function getFormulaValidationVariables(): array
    {
        return [
            'x' => 1.0,
            'min' => 0.0,
            'max' => 1.0,
            'avg' => 0.5,
            'count' => 10,
        ];
    }
}
