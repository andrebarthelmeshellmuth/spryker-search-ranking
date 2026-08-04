<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Persistence;

use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStoragePersistenceFactory getFactory()
 */
class SearchRankingStorageEntityManager extends AbstractEntityManager implements SearchRankingStorageEntityManagerInterface
{
    /**
     * @param array<string, mixed> $configurationData
     * @param string $storeName
     * @param string $localeName
     */
    public function saveRankingConfiguration(array $configurationData, string $storeName, string $localeName): void
    {
        $storageEntity = $this->getFactory()
            ->createSearchRankingConfigurationStorageQuery()
            ->filterByStore($storeName)
            ->filterByLocale($localeName)
            ->findOneOrCreate();

        $storageEntity->setData($configurationData);
        $storageEntity->save();
    }
}
