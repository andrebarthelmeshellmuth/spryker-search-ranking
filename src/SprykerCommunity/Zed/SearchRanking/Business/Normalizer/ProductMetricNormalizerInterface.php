<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Normalizer;

use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;

interface ProductMetricNormalizerInterface
{
    /**
     * @return \Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer
     */
    public function normalize(): SearchRankingNormalizationResultTransfer;
}
