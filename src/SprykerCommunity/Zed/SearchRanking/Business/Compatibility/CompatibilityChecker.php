<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Compatibility;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface;

/**
 * Thin delegate to the client-layer probe (bypasses `Client\Catalog`/`Client\Search` entirely, since
 * both are unusable from Zed in this shop); kept as its own business class, despite doing no
 * transformation of its own, purely for unit-test isolation from the full facade/business-factory
 * wiring, mirroring {@see \SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizer}.
 */
class CompatibilityChecker implements CompatibilityCheckerInterface
{
    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface
     */
    protected SearchRankingToSearchRankingClientInterface $searchRankingClient;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface $searchRankingClient
     */
    public function __construct(SearchRankingToSearchRankingClientInterface $searchRankingClient)
    {
        $this->searchRankingClient = $searchRankingClient;
    }

    /**
     * @return \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer
     */
    public function checkCompatibility(): SearchRankingEngineCompatibilityTransfer
    {
        return $this->searchRankingClient->checkEngineCompatibility();
    }
}
