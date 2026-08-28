<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Dependency\Client;

interface SearchRankingToStorageClientInterface
{
    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, $value): void;
}
