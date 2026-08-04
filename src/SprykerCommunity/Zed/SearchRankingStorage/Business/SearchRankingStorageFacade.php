<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Business;

use Generated\Shared\Transfer\FilterTransfer;
use Spryker\Zed\Kernel\Business\AbstractFacade;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Business\SearchRankingStorageBusinessFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageRepositoryInterface getRepository()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManagerInterface getEntityManager()
 */
class SearchRankingStorageFacade extends AbstractFacade implements SearchRankingStorageFacadeInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function publishRankingConfiguration(): void
    {
        $this->getFactory()->createRankingConfigurationStorageWriter()->publishRankingConfiguration();
    }

    /**
     * {@inheritDoc}
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
    ): array {
        return $this->getRepository()->getSearchRankingConfigurationSynchronizationDataTransfers(
            $filterTransfer,
            $searchRankingConfigurationStorageIds,
        );
    }
}
