<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Compatibility;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;

interface CompatibilityCheckerInterface
{
    public function checkCompatibility(): SearchRankingEngineCompatibilityTransfer;
}
