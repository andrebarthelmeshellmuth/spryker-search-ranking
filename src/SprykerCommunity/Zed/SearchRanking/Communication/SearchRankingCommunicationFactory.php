<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication;

use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use SprykerCommunity\Zed\SearchRanking\Communication\Acl\BackOfficeAccessAnalyzer;
use SprykerCommunity\Zed\SearchRanking\Communication\Acl\BackOfficeAccessAnalyzerInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToAclFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToDataImportFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSearchRankingStorageFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSynchronizationFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToTranslatorFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingDependencyProvider;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface getRepository()
 */
class SearchRankingCommunicationFactory extends AbstractCommunicationFactory
{
    public function getSearchRankingStorageFacade(): SearchRankingToSearchRankingStorageFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_SEARCH_RANKING_STORAGE);
    }

    public function getEventFacade(): SearchRankingToEventFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_EVENT);
    }

    public function getSynchronizationFacade(): SearchRankingToSynchronizationFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_SYNCHRONIZATION);
    }

    public function getTranslatorFacade(): SearchRankingToTranslatorFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_TRANSLATOR);
    }

    public function getDataImportFacade(): SearchRankingToDataImportFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_DATA_IMPORT);
    }

    public function getAclFacade(): SearchRankingToAclFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingDependencyProvider::FACADE_ACL);
    }

    public function createBackOfficeAccessAnalyzer(): BackOfficeAccessAnalyzerInterface
    {
        return new BackOfficeAccessAnalyzer($this->getAclFacade());
    }
}
