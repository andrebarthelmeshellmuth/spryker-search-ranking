<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Persistence;

use Generated\Shared\Transfer\FilterTransfer;

interface SearchRankingStorageRepositoryInterface
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
    ): array;
}
