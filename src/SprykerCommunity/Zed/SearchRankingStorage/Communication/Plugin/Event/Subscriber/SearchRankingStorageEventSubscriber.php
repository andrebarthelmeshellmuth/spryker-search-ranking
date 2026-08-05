<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Communication\Plugin\Event\Subscriber;

use Spryker\Zed\Event\Dependency\EventCollectionInterface;
use Spryker\Zed\Event\Dependency\Plugin\EventSubscriberInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use SprykerCommunity\Shared\SearchRanking\SearchRankingEvents;
use SprykerCommunity\Zed\SearchRankingStorage\Communication\Plugin\Event\Listener\RankingConfigurationPublishListener;

/**
 * A project installing spryker-community/search-ranking must register this subscriber in its own
 * `Pyz\Zed\Event\EventDependencyProvider::getEventSubscriberCollection()` — see the package README's
 * Installation section. Without it, ranking configuration changes (weights, saturation points, metric
 * identity/weight/deletion) still save correctly in Zed, but never reach the synced key-value storage the
 * live storefront query reads.
 *
 * @method \SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Business\SearchRankingStorageFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Communication\SearchRankingStorageCommunicationFactory getFactory()
 */
class SearchRankingStorageEventSubscriber extends AbstractPlugin implements EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Spryker\Zed\Event\Dependency\EventCollectionInterface $eventCollection
     */
    public function getSubscribedEvents(EventCollectionInterface $eventCollection): EventCollectionInterface
    {
        return $this->addRankingConfigurationPublishListener($eventCollection);
    }

    /**
     * Handled directly (NOT queued) — the ranking configuration is a single, cheap-to-republish
     * per-store/locale resource, not a bulk entity collection, and this project runs no queue worker by
     * default. See the package README's Publish & Synchronize note for the full reasoning.
     *
     * @param \Spryker\Zed\Event\Dependency\EventCollectionInterface $eventCollection
     */
    protected function addRankingConfigurationPublishListener(EventCollectionInterface $eventCollection): EventCollectionInterface
    {
        return $eventCollection->addListener(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE, new RankingConfigurationPublishListener());
    }
}
