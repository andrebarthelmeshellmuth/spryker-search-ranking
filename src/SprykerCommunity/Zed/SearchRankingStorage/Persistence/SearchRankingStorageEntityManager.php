<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Persistence;

use Orm\Zed\SearchRankingStorage\Persistence\SpySearchRankingConfigurationStorage;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStoragePersistenceFactory getFactory()
 */
class SearchRankingStorageEntityManager extends AbstractEntityManager implements SearchRankingStorageEntityManagerInterface
{
    /**
     * @param array<string, mixed> $configurationData
     *
     * @return void
     */
    public function saveRankingConfiguration(array $configurationData): void
    {
        $storageEntity = $this->getFactory()
            ->createSearchRankingConfigurationStorageQuery()
            ->findOne();

        if ($storageEntity === null) {
            $storageEntity = new SpySearchRankingConfigurationStorage();
        }

        $storageEntity->setData($configurationData);
        $storageEntity->save();
    }
}
