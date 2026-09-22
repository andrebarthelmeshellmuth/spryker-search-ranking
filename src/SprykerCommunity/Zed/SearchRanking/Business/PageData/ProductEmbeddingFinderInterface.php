<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\PageData;

interface ProductEmbeddingFinderInterface
{
    /**
     * @param int $idProductAbstract
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<int, float>|null
     */
    public function find(int $idProductAbstract, string $storeName, string $localeName): ?array;
}
