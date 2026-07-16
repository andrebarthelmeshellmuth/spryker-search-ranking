<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Business\Writer;

use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeInterface;
use SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface;

class RankingConfigurationStorageWriter implements RankingConfigurationStorageWriterInterface
{
    /**
     * @var string
     */
    protected const KEY_METRIC_WEIGHTS = 'metric_weights';

    /**
     * @var string
     */
    protected const KEY_SCORE_FLOOR = 'score_floor';

    /**
     * @var \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface
     */
    protected SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade;

    /**
     * @var \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface
     */
    protected SearchRankingStorageEntityManagerInterface $entityManager;

    /**
     * @var \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeInterface
     */
    protected SearchRankingStorageToSynchronizationFacadeInterface $synchronizationFacade;

    /**
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRankingStorage\Dependency\Facade\SearchRankingStorageToSynchronizationFacadeInterface $synchronizationFacade
     */
    public function __construct(
        SearchRankingStorageToSearchRankingFacadeInterface $searchRankingFacade,
        SearchRankingStorageEntityManagerInterface $entityManager,
        SearchRankingStorageToSynchronizationFacadeInterface $synchronizationFacade,
    ) {
        $this->searchRankingFacade = $searchRankingFacade;
        $this->entityManager = $entityManager;
        $this->synchronizationFacade = $synchronizationFacade;
    }

    /**
     * @return void
     */
    public function publishRankingConfiguration(): void
    {
        $metricWeights = [];

        foreach ($this->searchRankingFacade->getActiveMetricCollection()->getMetrics() as $metricTransfer) {
            $metricWeights[$metricTransfer->getNameOrFail()] = $metricTransfer->getWeightOrFail();
        }

        $this->entityManager->saveRankingConfiguration([
            static::KEY_METRIC_WEIGHTS => $metricWeights,
            static::KEY_SCORE_FLOOR => $this->searchRankingFacade->getScoreFloor(),
        ]);

        // With direct synchronization the behavior only BUFFERS the write; core flushes the buffer
        // solely on console termination, so a save inside a Zed web request (settings form, metric
        // CRUD) would never reach key-value storage without this explicit flush. Harmless when the
        // buffer is empty or in console context.
        $this->synchronizationFacade->flushSynchronizationMessagesFromBuffer();
    }
}
