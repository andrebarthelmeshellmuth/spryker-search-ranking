<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchRankingStorage;

class SearchRankingStorageConfig
{
    /**
     * Specification:
     * - Key-value storage resource name of the ranking configuration document. Must match the
     *   synchronization behavior of spy_search_ranking_configuration_storage.
     *
     * @api
     *
     * @var string
     */
    public const SEARCH_RANKING_CONFIGURATION_RESOURCE_NAME = 'search_ranking_configuration';

    /**
     * Specification:
     * - Queue used to synchronize the ranking configuration into key-value storage. Must match the
     *   synchronization behavior of spy_search_ranking_configuration_storage.
     *
     * @api
     *
     * @var string
     */
    public const SYNC_STORAGE_SEARCH_RANKING_QUEUE = 'sync.storage.search_ranking';
}
