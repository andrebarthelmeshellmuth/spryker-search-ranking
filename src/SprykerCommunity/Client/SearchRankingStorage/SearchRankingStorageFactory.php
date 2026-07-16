<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRankingStorage;

use Spryker\Client\Kernel\AbstractFactory;
use SprykerCommunity\Client\SearchRankingStorage\Dependency\Client\SearchRankingStorageToStorageClientInterface;
use SprykerCommunity\Client\SearchRankingStorage\Dependency\Service\SearchRankingStorageToSynchronizationServiceInterface;
use SprykerCommunity\Client\SearchRankingStorage\Reader\ConfigurationStorageReader;
use SprykerCommunity\Client\SearchRankingStorage\Reader\ConfigurationStorageReaderInterface;

class SearchRankingStorageFactory extends AbstractFactory
{
    /**
     * @return \SprykerCommunity\Client\SearchRankingStorage\Reader\ConfigurationStorageReaderInterface
     */
    public function createConfigurationStorageReader(): ConfigurationStorageReaderInterface
    {
        return new ConfigurationStorageReader(
            $this->getStorageClient(),
            $this->getSynchronizationService(),
        );
    }

    /**
     * @return \SprykerCommunity\Client\SearchRankingStorage\Dependency\Client\SearchRankingStorageToStorageClientInterface
     */
    public function getStorageClient(): SearchRankingStorageToStorageClientInterface
    {
        return $this->getProvidedDependency(SearchRankingStorageDependencyProvider::CLIENT_STORAGE);
    }

    /**
     * @return \SprykerCommunity\Client\SearchRankingStorage\Dependency\Service\SearchRankingStorageToSynchronizationServiceInterface
     */
    public function getSynchronizationService(): SearchRankingStorageToSynchronizationServiceInterface
    {
        return $this->getProvidedDependency(SearchRankingStorageDependencyProvider::SERVICE_SYNCHRONIZATION);
    }
}
