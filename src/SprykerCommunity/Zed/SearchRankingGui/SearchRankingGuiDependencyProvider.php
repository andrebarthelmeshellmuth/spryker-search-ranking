<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui;

use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToLocaleFacadeBridge;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeBridge;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingStorageFacadeBridge;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToStoreFacadeBridge;

class SearchRankingGuiDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FACADE_SEARCH_RANKING = 'FACADE_SEARCH_RANKING';

    /**
     * @var string
     */
    public const FACADE_SEARCH_RANKING_STORAGE = 'FACADE_SEARCH_RANKING_STORAGE';

    /**
     * @var string
     */
    public const PROPEL_QUERY_SEARCH_RANKING_METRIC = 'PROPEL_QUERY_SEARCH_RANKING_METRIC';

    /**
     * @var string
     */
    public const PROPEL_QUERY_SEARCH_RANKING_PRODUCT_METRIC = 'PROPEL_QUERY_SEARCH_RANKING_PRODUCT_METRIC';

    /**
     * @var string
     */
    public const PROPEL_QUERY_SEARCH_RANKING_METRIC_HISTORY = 'PROPEL_QUERY_SEARCH_RANKING_METRIC_HISTORY';

    /**
     * @var string
     */
    public const FACADE_STORE = 'FACADE_STORE';

    /**
     * @var string
     */
    public const FACADE_LOCALE = 'FACADE_LOCALE';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    #[\Override]
    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);
        $container = $this->addSearchRankingFacade($container);
        $container = $this->addSearchRankingStorageFacade($container);
        $container = $this->addSearchRankingMetricPropelQuery($container);
        $container = $this->addSearchRankingProductMetricPropelQuery($container);
        $container = $this->addSearchRankingMetricHistoryPropelQuery($container);
        $container = $this->addStoreFacade($container);
        $container = $this->addLocaleFacade($container);

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addStoreFacade(Container $container): Container
    {
        $container->set(static::FACADE_STORE, fn (Container $container) => new SearchRankingGuiToStoreFacadeBridge(
            $container->getLocator()->store()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addLocaleFacade(Container $container): Container
    {
        $container->set(static::FACADE_LOCALE, fn (Container $container) => new SearchRankingGuiToLocaleFacadeBridge(
            $container->getLocator()->locale()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSearchRankingStorageFacade(Container $container): Container
    {
        $container->set(static::FACADE_SEARCH_RANKING_STORAGE, fn (Container $container) => new SearchRankingGuiToSearchRankingStorageFacadeBridge(
            $container->getLocator()->searchRankingStorage()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSearchRankingFacade(Container $container): Container
    {
        $container->set(static::FACADE_SEARCH_RANKING, fn (Container $container) => new SearchRankingGuiToSearchRankingFacadeBridge(
            $container->getLocator()->searchRanking()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSearchRankingMetricPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_SEARCH_RANKING_METRIC, $container->factory(fn () => SpySearchRankingMetricQuery::create()));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSearchRankingProductMetricPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_SEARCH_RANKING_PRODUCT_METRIC, $container->factory(fn () => SpySearchRankingProductMetricQuery::create()));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSearchRankingMetricHistoryPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_SEARCH_RANKING_METRIC_HISTORY, $container->factory(fn () => SpySearchRankingMetricHistoryQuery::create()));

        return $container;
    }
}
