<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

interface SearchRankingStorageToSearchRankingFacadeInterface
{
    /**
     * The whole live configuration for one scope in a single read — see `SearchRanking`'s own facade for
     * the full specification. Metric weights arrive raw here; normalizing them is this module's own job
     * (see {@see \SprykerCommunity\Zed\SearchRankingStorage\Business\Writer\RankingConfigurationStorageWriter}),
     * because only the published document needs weights summing to 1.
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getConfiguration(string $storeName, string $localeName): SearchRankingConfigurationStorageTransfer;
}
