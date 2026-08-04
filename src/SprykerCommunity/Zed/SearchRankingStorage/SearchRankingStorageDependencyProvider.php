<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage;

use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeBridge;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToStoreFacadeBridge;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeBridge;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageConfig getConfig()
 */
class SearchRankingStorageDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FACADE_SEARCH_RANKING = 'FACADE_SEARCH_RANKING';

    /**
     * @var string
     */
    public const FACADE_SYNCHRONIZATION = 'FACADE_SYNCHRONIZATION';

    /**
     * @var string
     */
    public const FACADE_STORE = 'FACADE_STORE';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    #[\Override]
    public function provideBusinessLayerDependencies(Container $container): Container
    {
        $container = parent::provideBusinessLayerDependencies($container);
        $container = $this->addSearchRankingFacade($container);
        $container = $this->addSynchronizationFacade($container);
        $container = $this->addStoreFacade($container);

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addStoreFacade(Container $container): Container
    {
        $container->set(static::FACADE_STORE, fn (Container $container) => new SearchRankingStorageToStoreFacadeBridge(
            $container->getLocator()->store()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addSynchronizationFacade(Container $container): Container
    {
        $container->set(static::FACADE_SYNCHRONIZATION, fn (Container $container) => new SearchRankingStorageToSynchronizationFacadeBridge(
            $container->getLocator()->synchronization()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addSearchRankingFacade(Container $container): Container
    {
        $container->set(static::FACADE_SEARCH_RANKING, fn (Container $container) => new SearchRankingStorageToSearchRankingFacadeBridge(
            $container->getLocator()->searchRanking()->facade(),
        ));

        return $container;
    }
}
