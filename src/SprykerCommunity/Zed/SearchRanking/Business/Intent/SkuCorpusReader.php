<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

use Orm\Zed\Product\Persistence\Map\SpyProductAbstractStoreTableMap;
use Orm\Zed\Product\Persistence\Map\SpyProductAbstractTableMap;
use Orm\Zed\Product\Persistence\Map\SpyProductTableMap;
use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;
use Orm\Zed\Product\Persistence\SpyProductAbstractStoreQuery;
use Orm\Zed\Product\Persistence\SpyProductQuery;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;

/**
 * One of this package's shipped default {@see EntityCorpusReaderPluginInterface} implementations — see that
 * interface's own docblock for the extension seam this participates in. Also implements
 * {@see IncrementalEntityCorpusReaderPluginInterface} — see that interface's own docblock for the
 * event-pipeline incremental-sync seam this additionally participates in.
 */
class SkuCorpusReader implements SkuCorpusReaderInterface, IncrementalEntityCorpusReaderPluginInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getEntityType(): string
    {
        return SearchRankingConfig::ENTITY_LOOKUP_TYPE_SKU;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $idLocale is part of the shared
     * {@see EntityCorpusReaderPluginInterface} signature — a SKU has no locale dimension.
     *
     * @param int $idStore
     * @param int|null $idLocale
     *
     * @return array<int, string>
     */
    public function getTerms(int $idStore, ?int $idLocale): array
    {
        return $this->getSkus($idStore);
    }

    /**
     * {@inheritDoc}
     *
     * @param int|null $idStore
     *
     * @return array<int, string>
     */
    public function getSkus(?int $idStore): array
    {
        $idProductAbstractsForStore = $idStore !== null ? $this->getIdProductAbstractsForStore($idStore) : null;
        $activeIdProductAbstracts = $this->getActiveIdProductAbstracts($idProductAbstractsForStore);

        $skus = array_merge(
            $this->getAbstractSkus($activeIdProductAbstracts),
            $this->getConcreteSkus($activeIdProductAbstracts),
        );

        return array_values(array_unique(array_filter($skus, static fn (string $sku): bool => $sku !== '')));
    }

    /**
     * @param int $idStore
     *
     * @return array<int, int>
     */
    protected function getIdProductAbstractsForStore(int $idStore): array
    {
        return array_map(
            static fn (mixed $value): int => (int)$value,
            SpyProductAbstractStoreQuery::create()
                ->filterByFkStore($idStore)
                ->select([SpyProductAbstractStoreTableMap::COL_FK_PRODUCT_ABSTRACT])
                ->find()
                ->getData(),
        );
    }

    /**
     * Every product-abstract id that has at least one active concrete product — same "at least one active
     * concrete" rule `Spryker\Zed\ProductPageSearch\Business\Publisher\ProductAbstractPagePublisher::isActual()`
     * uses to decide whether an abstract stays in the main `page` index at all, applied here so a
     * delisted/inactive product's SKU (and, transitively, any brand/category value only reachable through
     * it — see {@see BrandCorpusReader}/{@see CategoryCorpusReader}) never enters the entity-lookup corpus
     * either. Deliberately checks `spy_product.is_active` only, not the fuller `is_searchable` combination
     * core's own publisher also checks (a store/price/stock-derived flag owned by the ProductSearch
     * module) — pulling that in would add a real module dependency this standalone package does not
     * otherwise need, for a signal `is_active` already covers the overwhelming majority of real "delisted"
     * cases for.
     *
     * @param array<int, int>|null $idProductAbstracts
     *
     * @return array<int, int>
     */
    protected function getActiveIdProductAbstracts(?array $idProductAbstracts): array
    {
        $query = SpyProductQuery::create()
            ->filterByIsActive(true)
            ->select([SpyProductTableMap::COL_FK_PRODUCT_ABSTRACT])
            ->distinct();

        if ($idProductAbstracts !== null) {
            $query->filterByFkProductAbstract_In($idProductAbstracts);
        }

        return array_map(static fn (mixed $value): int => (int)$value, $query->find()->getData());
    }

    /**
     * @param array<int, int> $idProductAbstracts
     *
     * @return array<int, string>
     */
    protected function getAbstractSkus(array $idProductAbstracts): array
    {
        if ($idProductAbstracts === []) {
            return [];
        }

        $query = SpyProductAbstractQuery::create()
            ->select([SpyProductAbstractTableMap::COL_SKU])
            ->filterByIdProductAbstract_In($idProductAbstracts);

        return array_map(static fn (mixed $value): string => (string)$value, $query->find()->getData());
    }

    /**
     * @param array<int, int> $idProductAbstracts
     *
     * @return array<int, string>
     */
    protected function getConcreteSkus(array $idProductAbstracts): array
    {
        if ($idProductAbstracts === []) {
            return [];
        }

        $query = SpyProductQuery::create()
            ->filterByIsActive(true)
            ->filterByFkProductAbstract_In($idProductAbstracts)
            ->select([SpyProductTableMap::COL_SKU]);

        return array_map(static fn (mixed $value): string => (string)$value, $query->find()->getData());
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idProductAbstract
     *
     * @return array<int, string>
     */
    public function getTermsForProductAbstract(int $idProductAbstract): array
    {
        $abstractSkus = array_map(
            static fn (mixed $value): string => (string)$value,
            SpyProductAbstractQuery::create()
                ->select([SpyProductAbstractTableMap::COL_SKU])
                ->filterByIdProductAbstract($idProductAbstract)
                ->find()
                ->getData(),
        );

        $concreteSkus = array_map(
            static fn (mixed $value): string => (string)$value,
            SpyProductQuery::create()
                ->select([SpyProductTableMap::COL_SKU])
                ->filterByFkProductAbstract($idProductAbstract)
                ->find()
                ->getData(),
        );

        $skus = array_merge($abstractSkus, $concreteSkus);

        return array_values(array_unique(array_filter($skus, static fn (string $sku): bool => $sku !== '')));
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idProductAbstract
     */
    public function isProductAbstractActive(int $idProductAbstract): bool
    {
        return $this->getActiveIdProductAbstracts([$idProductAbstract]) !== [];
    }

    /**
     * {@inheritDoc}
     *
     * A SKU is always unique to exactly one product — see this class's own docblock reference from
     * {@see IncrementalEntityCorpusReaderPluginInterface::isTermStillUsedElsewhere()}. Removal is therefore
     * unconditionally safe once the owning product is inactive; no other active product can share it.
     *
     * @api
     *
     * @param string $term
     * @param int $idProductAbstract
     * @param int $idStore
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- signature is fixed by IncrementalEntityCorpusReaderPluginInterface.
    public function isTermStillUsedElsewhere(string $term, int $idProductAbstract, int $idStore): bool
    {
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
        return false;
    }
}
