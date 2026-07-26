<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Communication;

use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;

/**
 * Empty on purpose: getFactory() resolves lazily, and the module's one Communication-layer class
 * (SearchRankingConfigurationSynchronizationDataPlugin) never calls it — only getFacade()/getConfig(),
 * which resolve independently of this class. Kept as the standard per-module Communication-layer
 * scaffold (a forward-compatible extension point), and because its absence would leave that plugin's
 * already-generated `@method ... getFactory()` docblock dangling.
 *
 * @method \SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Business\SearchRankingStorageFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageRepositoryInterface getRepository()
 */
class SearchRankingStorageCommunicationFactory extends AbstractCommunicationFactory
{
}
