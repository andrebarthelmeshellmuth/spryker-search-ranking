<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRankingStorage\Reader;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SynchronizationDataTransfer;
use SprykerCommunity\Client\SearchRankingStorage\Dependency\Client\SearchRankingStorageToStorageClientInterface;
use SprykerCommunity\Client\SearchRankingStorage\Dependency\Service\SearchRankingStorageToSynchronizationServiceInterface;
use SprykerCommunity\Shared\SearchRankingStorage\SearchRankingStorageConfig;

class ConfigurationStorageReader implements ConfigurationStorageReaderInterface
{
    /**
     * @var string
     */
    protected const KEY_METRIC_WEIGHTS = 'metric_weights';

    /**
     * @var string
     */
    protected const KEY_RELEVANCE_WEIGHT = 'relevance_weight';

    /**
     * @var string
     */
    protected const KEY_RELEVANCE_SATURATION_POINT = 'relevance_saturation_point';

    /**
     * @var \SprykerCommunity\Client\SearchRankingStorage\Dependency\Client\SearchRankingStorageToStorageClientInterface
     */
    protected SearchRankingStorageToStorageClientInterface $storageClient;

    /**
     * @var \SprykerCommunity\Client\SearchRankingStorage\Dependency\Service\SearchRankingStorageToSynchronizationServiceInterface
     */
    protected SearchRankingStorageToSynchronizationServiceInterface $synchronizationService;

    /**
     * Memoized per request; false = not read yet (null is a valid "no document" result). `static`, not
     * instance-level, matching Spryker core's own client-reader caching convention — safe under the
     * traditional process-per-request PHP-FPM model this project runs on, but would leak stale
     * configuration across requests under a persistent-worker runtime (RoadRunner/Swoole), since nothing
     * ever resets it mid-process.
     *
     * @var \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer|false|null
     */
    protected static $rankingConfigurationCache = false;

    /**
     * @param \SprykerCommunity\Client\SearchRankingStorage\Dependency\Client\SearchRankingStorageToStorageClientInterface $storageClient
     * @param \SprykerCommunity\Client\SearchRankingStorage\Dependency\Service\SearchRankingStorageToSynchronizationServiceInterface $synchronizationService
     */
    public function __construct(
        SearchRankingStorageToStorageClientInterface $storageClient,
        SearchRankingStorageToSynchronizationServiceInterface $synchronizationService,
    ) {
        $this->storageClient = $storageClient;
        $this->synchronizationService = $synchronizationService;
    }

    /**
     * @return \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer|null
     */
    public function findRankingConfiguration(): ?SearchRankingConfigurationStorageTransfer
    {
        if (static::$rankingConfigurationCache !== false) {
            return static::$rankingConfigurationCache;
        }

        static::$rankingConfigurationCache = $this->readRankingConfiguration();

        return static::$rankingConfigurationCache;
    }

    /**
     * @return \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer|null
     */
    protected function readRankingConfiguration(): ?SearchRankingConfigurationStorageTransfer
    {
        $storageKey = $this->synchronizationService
            ->getStorageKeyBuilder(SearchRankingStorageConfig::SEARCH_RANKING_CONFIGURATION_RESOURCE_NAME)
            ->generateKey(new SynchronizationDataTransfer());

        $configurationData = $this->storageClient->get($storageKey);

        if (!is_array($configurationData)) {
            return null;
        }

        return (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights($configurationData[static::KEY_METRIC_WEIGHTS] ?? [])
            ->setRelevanceWeight((float)($configurationData[static::KEY_RELEVANCE_WEIGHT] ?? 0.0))
            ->setRelevanceSaturationPoint((float)($configurationData[static::KEY_RELEVANCE_SATURATION_POINT] ?? 0.0));
    }
}
