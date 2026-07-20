<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Business;

use Generated\Shared\Transfer\FilterTransfer;

interface SearchRankingStorageFacadeInterface
{
    /**
     * Specification:
     * - Composes the ranking configuration document (weights of all active metrics + signal baseline)
     *   and writes it to the storage table; the synchronization behavior propagates it to
     *   key-value storage via the sync queue.
     *
     * @api
     *
     * @return void
     */
    public function publishRankingConfiguration(): void;

    /**
     * Specification:
     * - Returns synchronization data transfers of the stored configuration for sync replay
     *   (`sync:data search_ranking_configuration`).
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\FilterTransfer $filterTransfer
     * @param array<int> $searchRankingConfigurationStorageIds
     *
     * @return array<\Generated\Shared\Transfer\SynchronizationDataTransfer>
     */
    public function getSearchRankingConfigurationSynchronizationDataTransfers(
        FilterTransfer $filterTransfer,
        array $searchRankingConfigurationStorageIds = [],
    ): array;
}
