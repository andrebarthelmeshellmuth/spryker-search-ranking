<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRankingStorage;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Spryker\Client\Kernel\AbstractClient;

/**
 * @method \SprykerCommunity\Client\SearchRankingStorage\SearchRankingStorageFactory getFactory()
 */
class SearchRankingStorageClient extends AbstractClient implements SearchRankingStorageClientInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer|null
     */
    public function findRankingConfiguration(string $storeName, string $localeName): ?SearchRankingConfigurationStorageTransfer
    {
        return $this->getFactory()->createConfigurationStorageReader()->findRankingConfiguration($storeName, $localeName);
    }
}
