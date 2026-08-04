<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric;

use Codeception\Test\Unit;
use Orm\Zed\Product\Persistence\SpyProductAbstract;
use Spryker\Zed\DataImport\Business\Exception\EntityNotFoundException;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSet;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\ProductAbstractSkuToIdProductAbstractStep;

/**
 * INTEGRATION TEST — real database, real rows, never mocked: same rationale as
 * {@see MetricNameToIdSearchRankingMetricStepTest} — resolving a real SKU to a real primary key against
 * the real `spy_product_abstract` table is the entire job of this step.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingDataImport
 * @group Business
 * @group Writer
 * @group SearchRankingProductMetric
 * @group ProductAbstractSkuToIdProductAbstractStepTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRankingDataImport\SearchRankingDataImportZedTester $tester
 */
class ProductAbstractSkuToIdProductAbstractStepTest extends Unit
{
    /**
     * @var array<\Orm\Zed\Product\Persistence\SpyProductAbstract>
     */
    protected array $productAbstractEntities = [];

    protected function _after(): void
    {
        foreach ($this->productAbstractEntities as $productAbstractEntity) {
            $productAbstractEntity->delete();
        }

        parent::_after();
    }

    public function testExecuteResolvesAnExistingSkuToItsRealId(): void
    {
        // Arrange
        $productAbstractEntity = $this->createTestProductAbstract('test-resolvable-sku-' . uniqid());
        $dataSet = new DataSet([
            SearchRankingProductMetricDataSetInterface::COL_ABSTRACT_SKU => $productAbstractEntity->getSku(),
        ]);

        // Act
        (new ProductAbstractSkuToIdProductAbstractStep())->execute($dataSet);

        // Assert
        $this->assertSame(
            $productAbstractEntity->getIdProductAbstract(),
            $dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT],
        );
    }

    public function testExecuteThrowsWhenTheSkuDoesNotExist(): void
    {
        // Arrange
        $dataSet = new DataSet([
            SearchRankingProductMetricDataSetInterface::COL_ABSTRACT_SKU => 'test-never-imported-' . uniqid(),
        ]);

        // Act & Assert
        $this->expectException(EntityNotFoundException::class);

        (new ProductAbstractSkuToIdProductAbstractStep())->execute($dataSet);
    }

    /**
     * @param string $sku
     */
    protected function createTestProductAbstract(string $sku): SpyProductAbstract
    {
        $productAbstractEntity = new SpyProductAbstract();
        $productAbstractEntity->setSku($sku)
            ->setAttributes('{}')
            ->save();

        $this->productAbstractEntities[] = $productAbstractEntity;

        return $productAbstractEntity;
    }
}
