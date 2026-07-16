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
     * - Reads the ranking configuration document (active metric weights + score floor) from
     *   key-value storage.
     * - Returns null when the document was never synchronized.
     * - Memoizes the result for the rest of the request.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer|null
     */
    public function findRankingConfiguration(): ?SearchRankingConfigurationStorageTransfer;
}
