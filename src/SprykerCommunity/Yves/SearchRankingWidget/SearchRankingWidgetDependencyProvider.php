<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchRankingWidget;

use Spryker\Yves\Kernel\AbstractBundleDependencyProvider;
use Spryker\Yves\Kernel\Container;
use SprykerCommunity\Yves\SearchRankingWidget\Dependency\Client\SearchRankingWidgetToCatalogClientBridge;

class SearchRankingWidgetDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const CLIENT_CATALOG = 'CLIENT_CATALOG';

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     */
    #[\Override]
    public function provideDependencies(Container $container): Container
    {
        $container = parent::provideDependencies($container);

        return $this->addCatalogClient($container);
    }

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     */
    protected function addCatalogClient(Container $container): Container
    {
        $container->set(static::CLIENT_CATALOG, static fn (Container $container) => new SearchRankingWidgetToCatalogClientBridge($container->getLocator()->catalog()->client()));

        return $container;
    }
}
