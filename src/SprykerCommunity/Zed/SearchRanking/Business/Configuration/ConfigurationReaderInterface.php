<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Configuration;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

interface ConfigurationReaderInterface
{
    /**
     * Assembles the complete live ranking configuration for one (store, locale) from Zed's own settings
     * and metric rows — see {@see \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface::getConfiguration()}
     * for the full specification.
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getConfiguration(string $storeName, string $localeName): SearchRankingConfigurationStorageTransfer;
}
