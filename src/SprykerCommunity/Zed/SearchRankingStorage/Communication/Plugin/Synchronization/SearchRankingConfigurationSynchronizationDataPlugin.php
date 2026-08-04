<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Communication\Plugin\Synchronization;

use Generated\Shared\Transfer\FilterTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataBulkRepositoryPluginInterface;
use SprykerCommunity\Shared\SearchRankingStorage\SearchRankingStorageConfig;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Business\SearchRankingStorageFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Communication\SearchRankingStorageCommunicationFactory getFactory()
 */
class SearchRankingConfigurationSynchronizationDataPlugin extends AbstractPlugin implements SynchronizationDataBulkRepositoryPluginInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getResourceName(): string
    {
        return SearchRankingStorageConfig::SEARCH_RANKING_CONFIGURATION_RESOURCE_NAME;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function hasStore(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getQueueName(): string
    {
        return SearchRankingStorageConfig::SYNC_STORAGE_SEARCH_RANKING_QUEUE;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getSynchronizationQueuePoolName(): ?string
    {
        return $this->getConfig()->getSearchRankingSynchronizationPoolName();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $offset
     * @param int $limit
     * @param array<int> $ids
     *
     * @return array<\Generated\Shared\Transfer\SynchronizationDataTransfer>
     */
    public function getData(int $offset, int $limit, array $ids = []): array
    {
        $filterTransfer = (new FilterTransfer())
            ->setOffset($offset)
            ->setLimit($limit);

        return $this->getFacade()->getSearchRankingConfigurationSynchronizationDataTransfers($filterTransfer, $ids);
    }
}
