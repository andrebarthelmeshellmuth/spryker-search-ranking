<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Communication;

use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Business\SearchRankingStorageFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageRepositoryInterface getRepository()
 */
class SearchRankingStorageCommunicationFactory extends AbstractCommunicationFactory
{
}
