<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking;

use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSearchRankingStorageFacadeBridge;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FACADE_EVENT = 'FACADE_EVENT';

    /**
     * @var string
     */
    public const FACADE_SEARCH_RANKING_STORAGE = 'FACADE_SEARCH_RANKING_STORAGE';

    /**
     * @var string
     */
    public const CLIENT_SEARCH_RANKING = 'CLIENT_SEARCH_RANKING';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    public function provideBusinessLayerDependencies(Container $container): Container
    {
        $container = parent::provideBusinessLayerDependencies($container);
        $container = $this->addEventFacade($container);
        $container = $this->addSearchRankingClient($container);

        return $container;
    }

    /**
     * The storage facade is a Communication-layer dependency only (console command); the Business
     * layer must stay free of it to avoid a circular module dependency with SearchRankingStorage.
     *
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);
        $container = $this->addSearchRankingStorageFacade($container);

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addSearchRankingStorageFacade(Container $container): Container
    {
        $container->set(static::FACADE_SEARCH_RANKING_STORAGE, function (Container $container) {
            return new SearchRankingToSearchRankingStorageFacadeBridge(
                $container->getLocator()->searchRankingStorage()->facade(),
            );
        });

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addEventFacade(Container $container): Container
    {
        $container->set(static::FACADE_EVENT, function (Container $container) {
            return new SearchRankingToEventFacadeBridge(
                $container->getLocator()->event()->facade(),
            );
        });

        return $container;
    }

    /**
     * Used by the engine-compatibility check (`search-ranking:check-compatibility`) to probe the live
     * search engine's capabilities directly, bypassing `Client\Catalog`/`Client\Search` (which are
     * unusable from Zed in this shop).
     *
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addSearchRankingClient(Container $container): Container
    {
        $container->set(static::CLIENT_SEARCH_RANKING, function (Container $container) {
            return new SearchRankingToSearchRankingClientBridge(
                $container->getLocator()->searchRanking()->client(),
            );
        });

        return $container;
    }
}
