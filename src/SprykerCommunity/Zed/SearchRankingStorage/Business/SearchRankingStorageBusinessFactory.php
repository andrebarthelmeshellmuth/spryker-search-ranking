<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Business;

use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use SprykerCommunity\Zed\SearchRankingStorage\Business\Writer\RankingConfigurationStorageWriter;
use SprykerCommunity\Zed\SearchRankingStorage\Business\Writer\RankingConfigurationStorageWriterInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageDependencyProvider;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageRepositoryInterface getRepository()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface getEntityManager()
 */
class SearchRankingStorageBusinessFactory extends AbstractBusinessFactory
{
    public function createRankingConfigurationStorageWriter(): RankingConfigurationStorageWriterInterface
    {
        return new RankingConfigurationStorageWriter(
            $this->getSearchRankingFacade(),
            $this->getEntityManager(),
            $this->getSynchronizationFacade(),
            $this->getStoreFacade(),
        );
    }

    public function getSearchRankingFacade(): SearchRankingStorageToSearchRankingFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingStorageDependencyProvider::FACADE_SEARCH_RANKING);
    }

    public function getSynchronizationFacade(): SearchRankingStorageToSynchronizationFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingStorageDependencyProvider::FACADE_SYNCHRONIZATION);
    }

    public function getStoreFacade(): SearchRankingStorageToStoreFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingStorageDependencyProvider::FACADE_STORE);
    }
}
