<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Metric;

interface WeightNormalizerInterface
{
    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return bool
     */
    public function normalizeActiveWeights(string $storeName, string $localeName): bool;
}
