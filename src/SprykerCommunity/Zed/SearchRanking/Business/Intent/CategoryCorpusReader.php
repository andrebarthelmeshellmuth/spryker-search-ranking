<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

use Orm\Zed\Category\Persistence\Map\SpyCategoryAttributeTableMap;
use Orm\Zed\Category\Persistence\Map\SpyCategoryStoreTableMap;
use Orm\Zed\Category\Persistence\SpyCategoryAttributeQuery;
use Orm\Zed\Category\Persistence\SpyCategoryStoreQuery;
use Orm\Zed\Product\Persistence\Map\SpyProductAbstractStoreTableMap;
use Orm\Zed\Product\Persistence\Map\SpyProductTableMap;
use Orm\Zed\Product\Persistence\SpyProductAbstractStoreQuery;
use Orm\Zed\Product\Persistence\SpyProductQuery;
use Orm\Zed\ProductCategory\Persistence\Map\SpyProductCategoryTableMap;
use Orm\Zed\ProductCategory\Persistence\SpyProductCategoryQuery;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;

/**
 * One of this package's shipped default {@see EntityCorpusReaderPluginInterface} implementations — see that
 * interface's own docblock for the extension seam this participates in. Also implements
 * {@see IncrementalEntityCorpusReaderPluginInterface} — see that interface's own docblock for the
 * event-pipeline incremental-sync seam this additionally participates in.
 */
class CategoryCorpusReader implements CategoryCorpusReaderInterface, IncrementalEntityCorpusReaderPluginInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getEntityType(): string
    {
        return SearchRankingConfig::ENTITY_LOOKUP_TYPE_CATEGORY;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idStore
     * @param int|null $idLocale
     *
     * @return array<int, string>
     */
    public function getTerms(int $idStore, ?int $idLocale): array
    {
        return $this->getCategories($idStore, $idLocale);
    }

    /**
     * {@inheritDoc}
     *
     * @param int|null $idStore
     * @param int|null $idLocale
     *
     * @return array<int, string>
     */
    public function getCategories(?int $idStore, ?int $idLocale): array
    {
        $idCategories = $this->getIdCategoriesWithActiveProducts($idStore);

        if ($idCategories === []) {
            return [];
        }

        $query = SpyCategoryAttributeQuery::create()
            ->select([SpyCategoryAttributeTableMap::COL_NAME])
            ->filterByFkCategory_In($idCategories);

        if ($idLocale !== null) {
            $query->filterByFkLocale($idLocale);
        }

        $categories = array_filter(
            array_map('strval', $query->find()->getData()),
            static fn (string $category): bool => trim($category) !== '',
        );

        return array_values(array_unique($categories));
    }

    /**
     * @param int $idStore
     *
     * @return array<int, int>
     */
    protected function getIdCategoriesForStore(int $idStore): array
    {
        return array_map(
            'intval',
            SpyCategoryStoreQuery::create()
                ->filterByFkStore($idStore)
                ->select([SpyCategoryStoreTableMap::COL_FK_CATEGORY])
                ->find()
                ->getData(),
        );
    }

    /**
     * @param int $idStore
     *
     * @return array<int, int>
     */
    protected function getIdProductAbstractsForStore(int $idStore): array
    {
        return array_map(
            'intval',
            SpyProductAbstractStoreQuery::create()
                ->filterByFkStore($idStore)
                ->select([SpyProductAbstractStoreTableMap::COL_FK_PRODUCT_ABSTRACT])
                ->find()
                ->getData(),
        );
    }

    /**
     * Same "at least one active concrete" rule {@see SkuCorpusReader::getActiveIdProductAbstracts()}
     * applies to the product abstracts backing each category.
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

        return array_map('intval', $query->find()->getData());
    }

    /**
     * Every category id reachable through at least one active, non-delisted product-abstract — see
     * {@see getActiveIdProductAbstracts()} — additionally intersected with the store's own category
     * assignment (`spy_category_store`) when `$idStore` is given, preserving this reader's original
     * store-scoping mechanism unchanged.
     *
     * @param int|null $idStore
     *
     * @return array<int, int>
     */
    protected function getIdCategoriesWithActiveProducts(?int $idStore): array
    {
        $idProductAbstractsForStore = $idStore !== null ? $this->getIdProductAbstractsForStore($idStore) : null;
        $activeIdProductAbstracts = $this->getActiveIdProductAbstracts($idProductAbstractsForStore);

        if ($activeIdProductAbstracts === []) {
            return [];
        }

        $idCategoriesWithActiveProducts = array_map(
            'intval',
            SpyProductCategoryQuery::create()
                ->filterByFkProductAbstract_In($activeIdProductAbstracts)
                ->select([SpyProductCategoryTableMap::COL_FK_CATEGORY])
                ->distinct()
                ->find()
                ->getData(),
        );

        if ($idStore === null) {
            return $idCategoriesWithActiveProducts;
        }

        return array_values(array_intersect($idCategoriesWithActiveProducts, $this->getIdCategoriesForStore($idStore)));
    }

    /**
     * {@inheritDoc}
     *
     * Every locale's category name for every category `$idProductAbstract` is directly assigned to —
     * store-unscoped (unlike {@see getCategories()}, which optionally filters by store) and
     * active-state-unscoped (the caller, {@see EntityLookupIncrementalSyncer}, decides add vs. remove via
     * {@see isProductAbstractActive()} separately).
     *
     * @api
     *
     * @param int $idProductAbstract
     *
     * @return array<int, string>
     */
    public function getTermsForProductAbstract(int $idProductAbstract): array
    {
        $idCategories = array_map(
            'intval',
            SpyProductCategoryQuery::create()
                ->filterByFkProductAbstract($idProductAbstract)
                ->select([SpyProductCategoryTableMap::COL_FK_CATEGORY])
                ->distinct()
                ->find()
                ->getData(),
        );

        if ($idCategories === []) {
            return [];
        }

        $categories = array_filter(
            array_map('strval', SpyCategoryAttributeQuery::create()
                ->select([SpyCategoryAttributeTableMap::COL_NAME])
                ->filterByFkCategory_In($idCategories)
                ->find()
                ->getData()),
            static fn (string $category): bool => trim($category) !== '',
        );

        return array_values(array_unique($categories));
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param int $idProductAbstract
     *
     * @return bool
     */
    public function isProductAbstractActive(int $idProductAbstract): bool
    {
        return $this->getActiveIdProductAbstracts([$idProductAbstract]) !== [];
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $term
     * @param int $idProductAbstract
     * @param int $idStore
     *
     * @return bool
     */
    public function isTermStillUsedElsewhere(string $term, int $idProductAbstract, int $idStore): bool
    {
        $idCategoriesWithOtherActiveProducts = $this->getIdCategoriesWithOtherActiveProducts($idStore, $idProductAbstract);

        if ($idCategoriesWithOtherActiveProducts === []) {
            return false;
        }

        $categoryNames = array_map(
            'strval',
            SpyCategoryAttributeQuery::create()
                ->select([SpyCategoryAttributeTableMap::COL_NAME])
                ->filterByFkCategory_In($idCategoriesWithOtherActiveProducts)
                ->find()
                ->getData(),
        );

        return in_array($term, $categoryNames, true);
    }

    /**
     * Same as {@see getIdCategoriesWithActiveProducts()}, additionally excluding `$idProductAbstractToExclude`
     * from the "active products" set — used by {@see isTermStillUsedElsewhere()}, which must ask "is this
     * category still reachable through some OTHER active product", not through the one being deactivated.
     *
     * @param int $idStore
     * @param int $idProductAbstractToExclude
     *
     * @return array<int, int>
     */
    protected function getIdCategoriesWithOtherActiveProducts(int $idStore, int $idProductAbstractToExclude): array
    {
        $idProductAbstractsForStore = $this->getIdProductAbstractsForStore($idStore);
        $activeIdProductAbstracts = $this->getActiveIdProductAbstracts($idProductAbstractsForStore);
        $otherActiveIdProductAbstracts = array_values(array_diff($activeIdProductAbstracts, [$idProductAbstractToExclude]));

        if ($otherActiveIdProductAbstracts === []) {
            return [];
        }

        $idCategoriesWithActiveProducts = array_map(
            'intval',
            SpyProductCategoryQuery::create()
                ->filterByFkProductAbstract_In($otherActiveIdProductAbstracts)
                ->select([SpyProductCategoryTableMap::COL_FK_CATEGORY])
                ->distinct()
                ->find()
                ->getData(),
        );

        return array_values(array_intersect($idCategoriesWithActiveProducts, $this->getIdCategoriesForStore($idStore)));
    }
}
