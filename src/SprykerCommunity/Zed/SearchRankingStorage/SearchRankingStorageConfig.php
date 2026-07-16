<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage;

use Spryker\Zed\Kernel\AbstractBundleConfig;

class SearchRankingStorageConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Queue pool for broadcasting the configuration sync message to all store connections.
     *   A store-less synchronization resource MUST have a queue pool, otherwise message creation
     *   fails ("You must specify either store column or SynchronizationQueuePoolName").
     *   'synchronizationPool' is the pool the standard demoshops define in RabbitMqConfig::getQueuePools().
     *
     * @api
     *
     * @return string|null
     */
    public function getSearchRankingSynchronizationPoolName(): ?string
    {
        return 'synchronizationPool';
    }
}
