<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui;

use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeBridge;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingStorageFacadeBridge;

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
    public const PROPEL_QUERY_PRODUCT_ABSTRACT = 'PROPEL_QUERY_PRODUCT_ABSTRACT';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);
        $container = $this->addSearchRankingFacade($container);
        $container = $this->addSearchRankingStorageFacade($container);
        $container = $this->addSearchRankingMetricPropelQuery($container);
        $container = $this->addSearchRankingProductMetricPropelQuery($container);
        $container = $this->addSearchRankingMetricHistoryPropelQuery($container);
        $container = $this->addProductAbstractPropelQuery($container);

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
            return new SearchRankingGuiToSearchRankingStorageFacadeBridge(
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
    protected function addSearchRankingFacade(Container $container): Container
    {
        $container->set(static::FACADE_SEARCH_RANKING, function (Container $container) {
            return new SearchRankingGuiToSearchRankingFacadeBridge(
                $container->getLocator()->searchRanking()->facade(),
            );
        });

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addSearchRankingMetricPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_SEARCH_RANKING_METRIC, $container->factory(function () {
            return SpySearchRankingMetricQuery::create();
        }));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addSearchRankingProductMetricPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_SEARCH_RANKING_PRODUCT_METRIC, $container->factory(function () {
            return SpySearchRankingProductMetricQuery::create();
        }));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addSearchRankingMetricHistoryPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_SEARCH_RANKING_METRIC_HISTORY, $container->factory(function () {
            return SpySearchRankingMetricHistoryQuery::create();
        }));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addProductAbstractPropelQuery(Container $container): Container
    {
        $container->set(static::PROPEL_QUERY_PRODUCT_ABSTRACT, $container->factory(function () {
            return SpyProductAbstractQuery::create();
        }));

        return $container;
    }
}
