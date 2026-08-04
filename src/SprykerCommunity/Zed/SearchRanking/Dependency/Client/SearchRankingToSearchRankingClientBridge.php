<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Dependency\Client;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;

class SearchRankingToSearchRankingClientBridge implements SearchRankingToSearchRankingClientInterface
{
    /**
     * @var \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface
     */
    protected $searchRankingClient;

    /**
     * @param \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface $searchRankingClient
     */
    public function __construct($searchRankingClient)
    {
        $this->searchRankingClient = $searchRankingClient;
    }

    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer
    {
        return $this->searchRankingClient->checkEngineCompatibility();
    }
}
