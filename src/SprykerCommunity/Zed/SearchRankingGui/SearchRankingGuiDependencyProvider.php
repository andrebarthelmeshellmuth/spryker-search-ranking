<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui;

use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeBridge;

class SearchRankingGuiDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FACADE_SEARCH_RANKING = 'FACADE_SEARCH_RANKING';

    /**
     * @var string
     */
    public const PROPEL_QUERY_SEARCH_RANKING_METRIC = 'PROPEL_QUERY_SEARCH_RANKING_METRIC';

    /**
     * @var string
     */
    public const PROPEL_QUERY_SEARCH_RANKING_PRODUCT_METRIC = 'PROPEL_QUERY_SEARCH_RANKING_PRODUCT_METRIC';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);
        $container = $this->addSearchRankingFacade($container);
        $container = $this->addSearchRankingMetricPropelQuery($container);
        $container = $this->addSearchRankingProductMetricPropelQuery($container);

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
}
