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
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToAclFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSearchRankingStorageFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSymfonyMailerFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\QueryContainer\SearchRankingToAclQueryContainerBridge;

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
     * @var string
     */
    public const FACADE_ACL = 'FACADE_ACL';

    /**
     * @var string
     */
    public const QUERY_CONTAINER_ACL = 'QUERY_CONTAINER_ACL';

    /**
     * @var string
     */
    public const FACADE_SYMFONY_MAILER = 'FACADE_SYMFONY_MAILER';

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
        $container = $this->addAclFacade($container);
        $container = $this->addAclQueryContainer($container);
        $container = $this->addSymfonyMailerFacade($container);

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
     * Used only by the calibration feature, to fire calibration search queries directly against
     * Elasticsearch (see `SprykerCommunity\Client\SearchRanking\Search\CalibrationSearcher` for why
     * `Client\Catalog`/`Client\Search` can't be used for this from Zed).
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

    /**
     * Used only by the auto-tune job to resolve which admins hold the notification role — see
     * `SprykerCommunity\Zed\SearchRanking\Business\AutoTune\AdminEmailResolver`.
     *
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addAclFacade(Container $container): Container
    {
        $container->set(static::FACADE_ACL, function (Container $container) {
            return new SearchRankingToAclFacadeBridge(
                $container->getLocator()->acl()->facade(),
            );
        });

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addAclQueryContainer(Container $container): Container
    {
        $container->set(static::QUERY_CONTAINER_ACL, function (Container $container) {
            return new SearchRankingToAclQueryContainerBridge(
                $container->getLocator()->acl()->queryContainer(),
            );
        });

        return $container;
    }

    /**
     * Used only by the auto-tune job to send its before/after summary email — the lower-level
     * `SymfonyMailerFacade::send()` path, deliberately bypassing `spryker/mail`'s mail-type-plugin system
     * entirely (no per-order-event template registration needed for a one-off, self-contained HTML body).
     *
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addSymfonyMailerFacade(Container $container): Container
    {
        $container->set(static::FACADE_SYMFONY_MAILER, function (Container $container) {
            return new SearchRankingToSymfonyMailerFacadeBridge(
                $container->getLocator()->symfonyMailer()->facade(),
            );
        });

        return $container;
    }
}
