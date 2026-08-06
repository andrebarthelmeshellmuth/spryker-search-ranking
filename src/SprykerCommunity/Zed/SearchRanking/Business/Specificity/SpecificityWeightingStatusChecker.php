<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Specificity;

use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface;

/**
 * Thin delegate to the Client-layer flag (the one actually project-overridable place this can be turned
 * on — see {@see \SprykerCommunity\Client\SearchRanking\SearchRankingConfig::isSpecificityWeightingEnabled()}'s
 * own docblock), so a GUI surface asking the Facade reports the SAME real, effective value the
 * query-building code checks, not a second value that would need to be kept in sync by hand. Kept as its
 * own business class, despite doing no transformation of its own, purely for unit-test isolation from
 * the full facade/business-factory wiring, mirroring
 * {@see \SprykerCommunity\Zed\SearchRanking\Business\Compatibility\CompatibilityChecker}.
 */
class SpecificityWeightingStatusChecker implements SpecificityWeightingStatusCheckerInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface $searchRankingClient
     */
    public function __construct(protected SearchRankingToSearchRankingClientInterface $searchRankingClient)
    {
    }

    public function isEnabled(): bool
    {
        return $this->searchRankingClient->isSpecificityWeightingEnabled();
    }
}
