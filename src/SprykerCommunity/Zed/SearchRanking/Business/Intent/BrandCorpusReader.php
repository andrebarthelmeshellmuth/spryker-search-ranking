<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

use Orm\Zed\Product\Persistence\Map\SpyProductAbstractLocalizedAttributesTableMap;
use Orm\Zed\Product\Persistence\Map\SpyProductAbstractStoreTableMap;
use Orm\Zed\Product\Persistence\Map\SpyProductTableMap;
use Orm\Zed\Product\Persistence\SpyProductAbstractLocalizedAttributesQuery;
use Orm\Zed\Product\Persistence\SpyProductAbstractStoreQuery;
use Orm\Zed\Product\Persistence\SpyProductQuery;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;

/**
 * One of this package's shipped default {@see EntityCorpusReaderPluginInterface} implementations — see that
 * interface's own docblock for the extension seam this participates in. Also implements
 * {@see IncrementalEntityCorpusReaderPluginInterface} — see that interface's own docblock for the
 * event-pipeline incremental-sync seam this additionally participates in.
 */
class BrandCorpusReader implements BrandCorpusReaderInterface, IncrementalEntityCorpusReaderPluginInterface
{
    /**
     * @var string
     */
    protected const ATTRIBUTE_KEY_BRAND = 'brand';

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getEntityType(): string
    {
        return SearchRankingConfig::ENTITY_LOOKUP_TYPE_BRAND;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $idLocale is part of the shared
     * {@see EntityCorpusReaderPluginInterface} signature — this reader scans every locale's row
     * (see {@see getBrands()}'s own docblock).
     *
     * @param int $idStore
     * @param int|null $idLocale
     *
     * @return array<int, string>
     */
    public function getTerms(int $idStore, ?int $idLocale): array
    {
        return $this->getBrands($idStore);
    }

    /**
     * {@inheritDoc}
     *
     * @param int|null $idStore
     *
     * @return array<int, string>
     */
    public function getBrands(?int $idStore): array
    {
        $idProductAbstractsForStore = $idStore !== null ? $this->getIdProductAbstractsForStore($idStore) : null;
        $activeIdProductAbstracts = $this->getActiveIdProductAbstracts($idProductAbstractsForStore);

        if ($activeIdProductAbstracts === []) {
            return [];
        }

        $query = SpyProductAbstractLocalizedAttributesQuery::create()
            ->select([SpyProductAbstractLocalizedAttributesTableMap::COL_ATTRIBUTES])
            ->filterByFkProductAbstract_In($activeIdProductAbstracts);

        $brands = [];

        foreach ($query->find()->getData() as $attributesJson) {
            $brand = $this->extractBrand($attributesJson);

            if ($brand === null) {
                continue;
            }

            $brands[] = $brand;
        }

        return array_values(array_unique($brands));
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
     * applies — a brand value reachable ONLY through a delisted/inactive product's abstract must not
     * surface here either.
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
     * @param mixed $attributesJson
     */
    protected function extractBrand(mixed $attributesJson): ?string
    {
        if (!is_string($attributesJson) || $attributesJson === '') {
            return null;
        }

        $attributes = json_decode($attributesJson, true);

        if (!is_array($attributes)) {
            return null;
        }

        $brand = $attributes[static::ATTRIBUTE_KEY_BRAND] ?? null;

        if (!is_string($brand) || trim($brand) === '') {
            return null;
        }

        return $brand;
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
        $query = SpyProductAbstractLocalizedAttributesQuery::create()
            ->select([SpyProductAbstractLocalizedAttributesTableMap::COL_ATTRIBUTES])
            ->filterByFkProductAbstract($idProductAbstract);

        $brands = [];

        foreach ($query->find()->getData() as $attributesJson) {
            $brand = $this->extractBrand($attributesJson);

            if ($brand === null) {
                continue;
            }

            $brands[] = $brand;
        }

        return array_values(array_unique($brands));
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
        $idProductAbstractsForStore = $this->getIdProductAbstractsForStore($idStore);
        $activeIdProductAbstracts = $this->getActiveIdProductAbstracts($idProductAbstractsForStore);
        $otherActiveIdProductAbstracts = array_values(array_diff($activeIdProductAbstracts, [$idProductAbstract]));

        if ($otherActiveIdProductAbstracts === []) {
            return false;
        }

        $query = SpyProductAbstractLocalizedAttributesQuery::create()
            ->select([SpyProductAbstractLocalizedAttributesTableMap::COL_ATTRIBUTES])
            ->filterByFkProductAbstract_In($otherActiveIdProductAbstracts);

        foreach ($query->find()->getData() as $attributesJson) {
            if ($this->extractBrand($attributesJson) === $term) {
                return true;
            }
        }

        return false;
    }
}
