<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric;

use Codeception\Test\Unit;
use Orm\Zed\Product\Persistence\SpyProductAbstract;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSet;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\SearchRankingProductMetricWriterStep;

/**
 * INTEGRATION TEST — real database, real rows, never mocked: this step's only job is a real Propel
 * upsert-by-foreign-keys, so the one behavior actually worth protecting — that re-importing the same
 * (metric, product abstract) pair updates the existing row's rawValue instead of creating a duplicate,
 * AND leaves an already-normalized normalizedValue untouched (see this class's own docblock: normalization
 * only ever happens downstream, in the normalize cron) — can only be proven against a real row.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingDataImport
 * @group Business
 * @group Writer
 * @group SearchRankingProductMetric
 * @group SearchRankingProductMetricWriterStepTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRankingDataImport\SearchRankingDataImportZedTester $tester
 */
class SearchRankingProductMetricWriterStepTest extends Unit
{
    /**
     * @var array<\Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric>
     */
    protected array $metricEntities = [];

    /**
     * @var array<\Orm\Zed\Product\Persistence\SpyProductAbstract>
     */
    protected array $productAbstractEntities = [];

    /**
     * @var array<\Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric>
     */
    protected array $productMetricEntities = [];

    /**
     * @return void
     */
    protected function _after(): void
    {
        foreach ($this->productMetricEntities as $productMetricEntity) {
            $productMetricEntity->delete();
        }

        foreach ($this->productAbstractEntities as $productAbstractEntity) {
            $productAbstractEntity->delete();
        }

        foreach ($this->metricEntities as $metricEntity) {
            $metricEntity->delete();
        }

        parent::_after();
    }

    /**
     * @return void
     */
    public function testExecuteCreatesANewProductMetricRowWhenNoneExistsYetForThatPair(): void
    {
        // Arrange
        $metricEntity = $this->createTestMetric('test_new_pm_metric_' . uniqid());
        $productAbstractEntity = $this->createTestProductAbstract('test-new-pm-sku-' . uniqid());

        $dataSet = new DataSet([
            SearchRankingProductMetricDataSetInterface::KEY_ID_SEARCH_RANKING_METRIC => $metricEntity->getIdSearchRankingMetric(),
            SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT => $productAbstractEntity->getIdProductAbstract(),
            SearchRankingProductMetricDataSetInterface::COL_RAW_VALUE => '42.5',
        ]);

        // Act
        (new SearchRankingProductMetricWriterStep())->execute($dataSet);

        // Assert
        $productMetricEntity = $this->findAndTrackProductMetric($metricEntity, $productAbstractEntity);

        $this->assertSame(42.5, $productMetricEntity->getRawValue());
        $this->assertNull($productMetricEntity->getNormalizedValue());
    }

    /**
     * @return void
     */
    public function testExecuteUpdatesRawValueButLeavesAnAlreadyNormalizedValueUntouched(): void
    {
        // Arrange
        $metricEntity = $this->createTestMetric('test_existing_pm_metric_' . uniqid());
        $productAbstractEntity = $this->createTestProductAbstract('test-existing-pm-sku-' . uniqid());

        $productMetricEntity = new SpySearchRankingProductMetric();
        $productMetricEntity->setFkSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
            ->setFkProductAbstract($productAbstractEntity->getIdProductAbstract())
            ->setRawValue(1.0)
            ->setNormalizedValue(0.75)
            ->save();
        $this->productMetricEntities[] = $productMetricEntity;

        $dataSet = new DataSet([
            SearchRankingProductMetricDataSetInterface::KEY_ID_SEARCH_RANKING_METRIC => $metricEntity->getIdSearchRankingMetric(),
            SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT => $productAbstractEntity->getIdProductAbstract(),
            SearchRankingProductMetricDataSetInterface::COL_RAW_VALUE => '99.0',
        ]);

        // Act
        (new SearchRankingProductMetricWriterStep())->execute($dataSet);

        // Assert
        $this->assertSame(
            1,
            SpySearchRankingProductMetricQuery::create()
                ->filterByFkSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
                ->filterByFkProductAbstract($productAbstractEntity->getIdProductAbstract())
                ->count(),
        );

        $updatedEntity = $this->findAndTrackProductMetric($metricEntity, $productAbstractEntity);
        $this->assertSame(99.0, $updatedEntity->getRawValue());
        $this->assertSame(0.75, $updatedEntity->getNormalizedValue());
    }

    /**
     * @param string $name
     *
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric
     */
    protected function createTestMetric(string $name): SpySearchRankingMetric
    {
        $metricEntity = new SpySearchRankingMetric();
        $metricEntity->setName($name)
            ->setWeight(1.0)
            ->setFormula('x')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->save();

        $this->metricEntities[] = $metricEntity;

        return $metricEntity;
    }

    /**
     * @param string $sku
     *
     * @return \Orm\Zed\Product\Persistence\SpyProductAbstract
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

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity
     * @param \Orm\Zed\Product\Persistence\SpyProductAbstract $productAbstractEntity
     *
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric
     */
    protected function findAndTrackProductMetric(
        SpySearchRankingMetric $metricEntity,
        SpyProductAbstract $productAbstractEntity,
    ): SpySearchRankingProductMetric {
        $productMetricEntity = SpySearchRankingProductMetricQuery::create()
            ->filterByFkSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
            ->filterByFkProductAbstract($productAbstractEntity->getIdProductAbstract())
            ->findOne();

        $this->assertNotNull($productMetricEntity);

        $this->productMetricEntities[] = $productMetricEntity;

        return $productMetricEntity;
    }
}
