<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

use Orm\Zed\Product\Persistence\Map\SpyProductAbstractStoreTableMap;
use Orm\Zed\Product\Persistence\SpyProductAbstractStoreQuery;

class ProductAbstractStoreResolver implements ProductAbstractStoreResolverInterface
{
    /**
     * {@inheritDoc}
     *
     * @param int $idProductAbstract
     *
     * @return array<int, int>
     */
    public function getIdStoresForProductAbstract(int $idProductAbstract): array
    {
        return array_map(
            static fn (mixed $value): int => (int)$value,
            SpyProductAbstractStoreQuery::create()
                ->filterByFkProductAbstract($idProductAbstract)
                ->select([SpyProductAbstractStoreTableMap::COL_FK_STORE])
                ->find()
                ->getData(),
        );
    }
}
