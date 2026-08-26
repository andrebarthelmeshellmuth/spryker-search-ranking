<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Intent;

use Codeception\Test\Unit;
use Orm\Zed\Category\Persistence\SpyCategoryAttributeQuery;
use Orm\Zed\Category\Persistence\SpyCategoryQuery;
use Orm\Zed\Category\Persistence\SpyCategoryStoreQuery;
use Orm\Zed\Category\Persistence\SpyCategoryTemplateQuery;
use Orm\Zed\Locale\Persistence\SpyLocaleQuery;
use Orm\Zed\Product\Persistence\SpyProductAbstractLocalizedAttributesQuery;
use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;
use Orm\Zed\Product\Persistence\SpyProductAbstractStoreQuery;
use Orm\Zed\Product\Persistence\SpyProductQuery;
use Orm\Zed\ProductCategory\Persistence\SpyProductCategoryQuery;
use Orm\Zed\Store\Persistence\SpyStoreQuery;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\BrandCorpusReader;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\CategoryCorpusReader;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SkuCorpusReader;

/**
 * INTEGRATION TEST — real-DB coverage for Task 3's active/sellable exclusion rule: a delisted/inactive
 * product's SKU (and any brand/category value ONLY reachable through it) must not appear in any of the
 * three shipped {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface}
 * implementations' output — mirroring
 * {@see \Spryker\Zed\ProductPageSearch\Business\Publisher\ProductAbstractPagePublisher::isActual()}'s own
 * "at least one active concrete" rule for the main `page` index.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Intent
 * @group EntityCorpusReaderSellableFilterTest
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group NeedsDatabase
 */
class EntityCorpusReaderSellableFilterTest extends Unit
{
    /**
     * @var string
     */
    protected const STORE_NAME = 'DE';

    /**
     * @var string
     */
    protected const LOCALE_NAME = 'de_DE';

    /**
     * @var string
     */
    protected const ACTIVE_ABSTRACT_SKU = 'zz-corpus-filter-active-abs';

    /**
     * @var string
     */
    protected const ACTIVE_CONCRETE_SKU = 'zz-corpus-filter-active-con';

    /**
     * @var string
     */
    protected const INACTIVE_ABSTRACT_SKU = 'zz-corpus-filter-inactive-abs';

    /**
     * @var string
     */
    protected const INACTIVE_CONCRETE_SKU = 'zz-corpus-filter-inactive-con';

    /**
     * @var string
     */
    protected const ACTIVE_BRAND = 'ZzCorpusFilterBrandActive';

    /**
     * @var string
     */
    protected const INACTIVE_BRAND = 'ZzCorpusFilterBrandInactive';

    /**
     * @var string
     */
    protected const ACTIVE_CATEGORY_NAME = 'ZzCorpusFilterCategoryActive';

    /**
     * @var string
     */
    protected const INACTIVE_CATEGORY_NAME = 'ZzCorpusFilterCategoryInactive';

    protected int $idStore;

    protected int $idLocale;

    protected function _before(): void
    {
        parent::_before();

        $storeEntity = SpyStoreQuery::create()->findOneByName(static::STORE_NAME);
        $this->assertNotNull($storeEntity, 'Setup: the "DE" store must exist for this test to run.');
        $this->idStore = $storeEntity->getIdStore();

        $localeEntity = SpyLocaleQuery::create()->findOneByLocaleName(static::LOCALE_NAME);
        $this->assertNotNull($localeEntity, 'Setup: the "de_DE" locale must exist for this test to run.');
        $this->idLocale = $localeEntity->getIdLocale();

        $this->createProductAbstractFixture(
            static::ACTIVE_ABSTRACT_SKU,
            static::ACTIVE_CONCRETE_SKU,
            true,
            static::ACTIVE_BRAND,
            static::ACTIVE_CATEGORY_NAME,
        );
        $this->createProductAbstractFixture(
            static::INACTIVE_ABSTRACT_SKU,
            static::INACTIVE_CONCRETE_SKU,
            false,
            static::INACTIVE_BRAND,
            static::INACTIVE_CATEGORY_NAME,
        );
    }

    public function testSkuCorpusReaderExcludesTermsReachableOnlyThroughAnInactiveProduct(): void
    {
        // Act
        $skus = (new SkuCorpusReader())->getTerms($this->idStore, null);

        // Assert
        $this->assertContains(static::ACTIVE_ABSTRACT_SKU, $skus);
        $this->assertContains(static::ACTIVE_CONCRETE_SKU, $skus);
        $this->assertNotContains(static::INACTIVE_ABSTRACT_SKU, $skus, 'A delisted product\'s abstract SKU must not appear in the entity-lookup corpus.');
        $this->assertNotContains(static::INACTIVE_CONCRETE_SKU, $skus, 'A delisted product\'s concrete SKU must not appear in the entity-lookup corpus.');
    }

    public function testBrandCorpusReaderExcludesABrandReachableOnlyThroughAnInactiveProduct(): void
    {
        // Act
        $brands = (new BrandCorpusReader())->getTerms($this->idStore, null);

        // Assert
        $this->assertContains(static::ACTIVE_BRAND, $brands);
        $this->assertNotContains(static::INACTIVE_BRAND, $brands, 'A brand reachable only through a delisted product must not appear in the entity-lookup corpus.');
    }

    public function testCategoryCorpusReaderExcludesACategoryReachableOnlyThroughAnInactiveProduct(): void
    {
        // Act
        $categories = (new CategoryCorpusReader())->getTerms($this->idStore, $this->idLocale);

        // Assert
        $this->assertContains(static::ACTIVE_CATEGORY_NAME, $categories);
        $this->assertNotContains(static::INACTIVE_CATEGORY_NAME, $categories, 'A category reachable only through a delisted product must not appear in the entity-lookup corpus.');
    }

    /**
     * @param string $abstractSku
     * @param string $concreteSku
     * @param bool $isConcreteActive
     * @param string $brand
     * @param string $categoryName
     */
    protected function createProductAbstractFixture(
        string $abstractSku,
        string $concreteSku,
        bool $isConcreteActive,
        string $brand,
        string $categoryName,
    ): void {
        $productAbstractEntity = SpyProductAbstractQuery::create()
            ->filterBySku($abstractSku)
            ->findOneOrCreate();
        $productAbstractEntity
            ->setAttributes('{}')
            ->save();
        $idProductAbstract = $productAbstractEntity->getIdProductAbstract();

        SpyProductAbstractLocalizedAttributesQuery::create()
            ->filterByFkProductAbstract($idProductAbstract)
            ->filterByFkLocale($this->idLocale)
            ->findOneOrCreate()
            ->setName($abstractSku)
            ->setAttributes((string)json_encode(['brand' => $brand]))
            ->save();

        SpyProductQuery::create()
            ->filterBySku($concreteSku)
            ->findOneOrCreate()
            ->setFkProductAbstract($idProductAbstract)
            ->setIsActive($isConcreteActive)
            ->setAttributes('{}')
            ->save();

        SpyProductAbstractStoreQuery::create()
            ->filterByFkProductAbstract($idProductAbstract)
            ->filterByFkStore($this->idStore)
            ->findOneOrCreate()
            ->save();

        $categoryTemplateEntity = SpyCategoryTemplateQuery::create()->findOne();
        $this->assertNotNull($categoryTemplateEntity, 'Setup: at least one category template must exist for this test to run.');

        $categoryEntity = SpyCategoryQuery::create()
            ->filterByCategoryKey($categoryName)
            ->findOneOrCreate();
        $categoryEntity
            ->setFkCategoryTemplate($categoryTemplateEntity->getIdCategoryTemplate())
            ->save();
        $idCategory = $categoryEntity->getIdCategory();

        SpyCategoryAttributeQuery::create()
            ->filterByFkCategory($idCategory)
            ->filterByFkLocale($this->idLocale)
            ->findOneOrCreate()
            ->setName($categoryName)
            ->save();

        SpyCategoryStoreQuery::create()
            ->filterByFkCategory($idCategory)
            ->filterByFkStore($this->idStore)
            ->findOneOrCreate()
            ->save();

        SpyProductCategoryQuery::create()
            ->filterByFkProductAbstract($idProductAbstract)
            ->filterByFkCategory($idCategory)
            ->findOneOrCreate()
            ->save();
    }
}
