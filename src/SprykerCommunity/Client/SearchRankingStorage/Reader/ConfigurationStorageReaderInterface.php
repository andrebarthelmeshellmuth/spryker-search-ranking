<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRankingStorage\Reader;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

interface ConfigurationStorageReaderInterface
{
    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer|null
     */
    public function findRankingConfiguration(string $storeName, string $localeName): ?SearchRankingConfigurationStorageTransfer;
}
