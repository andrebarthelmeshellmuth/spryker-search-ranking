<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Persistence;

use Orm\Zed\SearchRankingStorage\Persistence\SpySearchRankingConfigurationStorageQuery;
use Spryker\Zed\Kernel\Persistence\AbstractPersistenceFactory;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageRepositoryInterface getRepository()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface getEntityManager()
 */
class SearchRankingStoragePersistenceFactory extends AbstractPersistenceFactory
{
    /**
     * @return \Orm\Zed\SearchRankingStorage\Persistence\SpySearchRankingConfigurationStorageQuery
     */
    public function createSearchRankingConfigurationStorageQuery(): SpySearchRankingConfigurationStorageQuery
    {
        return SpySearchRankingConfigurationStorageQuery::create();
    }
}
