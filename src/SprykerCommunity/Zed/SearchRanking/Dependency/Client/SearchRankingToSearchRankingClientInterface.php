<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Dependency\Client;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;

interface SearchRankingToSearchRankingClientInterface
{
    /**
     * @return \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer;
}
