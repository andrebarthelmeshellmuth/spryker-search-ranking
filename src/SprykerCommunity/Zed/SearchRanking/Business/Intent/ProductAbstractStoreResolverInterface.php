<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

interface ProductAbstractStoreResolverInterface
{
    /**
     * Specification:
     * - Every `id_store` `$idProductAbstract` is assigned to, via `spy_product_abstract_store`. Empty when
     *   the product-abstract does not exist or is assigned to no store.
     *
     * @param int $idProductAbstract
     *
     * @return array<int, int>
     */
    public function getIdStoresForProductAbstract(int $idProductAbstract): array;
}
