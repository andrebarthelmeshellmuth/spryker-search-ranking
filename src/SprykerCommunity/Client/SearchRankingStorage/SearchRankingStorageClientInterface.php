<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRankingStorage;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

interface SearchRankingStorageClientInterface
{
    /**
     * Specification:
     * - Reads the ranking configuration document (active metric weights + relevanceWeight +
     *   relevanceSaturationPoint) for the given store and locale from key-value storage.
     * - Returns null when the document was never synchronized.
     * - Memoizes the result per (store, locale) for the rest of the request.
     *
     * @api
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function findRankingConfiguration(string $storeName, string $localeName): ?SearchRankingConfigurationStorageTransfer;
}
