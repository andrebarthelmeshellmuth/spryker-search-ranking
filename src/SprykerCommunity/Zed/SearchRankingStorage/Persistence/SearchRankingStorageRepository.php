<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Persistence;

use Generated\Shared\Transfer\FilterTransfer;
use Generated\Shared\Transfer\SynchronizationDataTransfer;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStoragePersistenceFactory getFactory()
 */
class SearchRankingStorageRepository extends AbstractRepository implements SearchRankingStorageRepositoryInterface
{
    /**
     * @param \Generated\Shared\Transfer\FilterTransfer $filterTransfer
     * @param array<int> $searchRankingConfigurationStorageIds
     *
     * @return array<\Generated\Shared\Transfer\SynchronizationDataTransfer>
     */
    public function getSearchRankingConfigurationSynchronizationDataTransfers(
        FilterTransfer $filterTransfer,
        array $searchRankingConfigurationStorageIds = [],
    ): array {
        $storageQuery = $this->getFactory()->createSearchRankingConfigurationStorageQuery();

        if ($searchRankingConfigurationStorageIds !== []) {
            $storageQuery->filterByIdSearchRankingConfigurationStorage_In($searchRankingConfigurationStorageIds);
        }

        $storageQuery
            ->offset($filterTransfer->getOffset() ?? 0)
            ->limit($filterTransfer->getLimit() ?? 100);

        $synchronizationDataTransfers = [];

        foreach ($storageQuery->find() as $storageEntity) {
            $synchronizationDataTransfers[] = (new SynchronizationDataTransfer())
                ->setData($storageEntity->getData())
                ->setKey($storageEntity->getKey())
                ->setStore($storageEntity->getStore())
                ->setLocale($storageEntity->getLocale());
        }

        return $synchronizationDataTransfers;
    }
}
