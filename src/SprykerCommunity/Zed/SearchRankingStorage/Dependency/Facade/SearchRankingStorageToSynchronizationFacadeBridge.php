<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade;

class SearchRankingStorageToSynchronizationFacadeBridge implements SearchRankingStorageToSynchronizationFacadeInterface
{
    /**
     * @var \Spryker\Zed\Synchronization\Business\SynchronizationFacadeInterface
     */
    protected $synchronizationFacade;

    /**
     * @param \Spryker\Zed\Synchronization\Business\SynchronizationFacadeInterface $synchronizationFacade
     */
    public function __construct($synchronizationFacade)
    {
        $this->synchronizationFacade = $synchronizationFacade;
    }

    /**
     * @return void
     */
    public function flushSynchronizationMessagesFromBuffer(): void
    {
        $this->synchronizationFacade->flushSynchronizationMessagesFromBuffer();
    }
}
