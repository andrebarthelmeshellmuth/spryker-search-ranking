<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Spryker\Client\Kernel\AbstractDependencyProvider;
use Spryker\Client\Kernel\Container;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToLocaleClientBridge;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToPermissionClientBridge;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientBridge;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientBridge;

class SearchRankingDependencyProvider extends AbstractDependencyProvider
{
    /**
     * @var string
     */
    public const CLIENT_SEARCH_RANKING_STORAGE = 'CLIENT_SEARCH_RANKING_STORAGE';

    /**
     * @var string
     */
    public const CLIENT_STORE = 'CLIENT_STORE';

    /**
     * @var string
     */
    public const CLIENT_LOCALE = 'CLIENT_LOCALE';

    /**
     * @var string
     */
    public const CLIENT_PERMISSION = 'CLIENT_PERMISSION';

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    #[\Override]
    public function provideServiceLayerDependencies(Container $container): Container
    {
        $container = parent::provideServiceLayerDependencies($container);
        $container = $this->addSearchRankingStorageClient($container);
        $container = $this->addStoreClient($container);
        $container = $this->addLocaleClient($container);
        $container = $this->addPermissionClient($container);

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addSearchRankingStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_SEARCH_RANKING_STORAGE, fn (Container $container) => new SearchRankingToSearchRankingStorageClientBridge(
            $container->getLocator()->searchRankingStorage()->client(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addStoreClient(Container $container): Container
    {
        $container->set(static::CLIENT_STORE, fn (Container $container) => new SearchRankingToStoreClientBridge(
            $container->getLocator()->store()->client(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addLocaleClient(Container $container): Container
    {
        $container->set(static::CLIENT_LOCALE, fn (Container $container) => new SearchRankingToLocaleClientBridge(
            $container->getLocator()->locale()->client(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addPermissionClient(Container $container): Container
    {
        $container->set(static::CLIENT_PERMISSION, fn (Container $container) => new SearchRankingToPermissionClientBridge(
            $container->getLocator()->permission()->client(),
        ));

        return $container;
    }
}
