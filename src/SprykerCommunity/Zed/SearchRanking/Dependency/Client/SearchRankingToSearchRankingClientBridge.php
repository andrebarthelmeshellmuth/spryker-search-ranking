<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Dependency\Client;

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

    /**
     * @param string $searchTerm
     * @param string $storeName
     * @param string $localeName
     * @param int $limit
     *
     * @return array<float>
     */
    public function getCalibrationScores(string $searchTerm, string $storeName, string $localeName, int $limit): array
    {
        return $this->searchRankingClient->getCalibrationScores($searchTerm, $storeName, $localeName, $limit);
    }
}
