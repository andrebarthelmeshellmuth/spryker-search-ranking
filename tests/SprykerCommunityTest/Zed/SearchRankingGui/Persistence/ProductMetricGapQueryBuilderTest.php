<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGui\Persistence;

use Codeception\Test\Unit;
use Orm\Zed\Product\Persistence\SpyProductAbstract;
use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric;
use Propel\Runtime\ActiveQuery\Criteria;
use SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapQueryBuilder;

/**
 * INTEGRATION TEST — writes real rows to the real test database (a metric, three product abstracts, and
 * one product-metric row), never mocked: the whole point of this class is a non-trivial Propel LEFT
 * JOIN + custom join condition + IS NULL, which a mocked query object could never actually prove correct.
 * All queries are scoped to this test's own three product abstract IDs, so a real, populated demoshop
 * catalog never leaks into (or slows down) the assertions.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingGui
 * @group Persistence
 * @group ProductMetricGapQueryBuilderTest
 *
 * @property \SprykerCommunityTest\Zed\SearchRankingGui\SearchRankingGuiZedTester $tester
 */
class ProductMetricGapQueryBuilderTest extends Unit
{
    /**
     * @var \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric|null
     */
    protected ?SpySearchRankingMetric $metricEntity = null;

    /**
     * @var array<\Orm\Zed\Product\Persistence\SpyProductAbstract>
     */
    protected array $productAbstractEntities = [];

    /**
     * @var \Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric|null
     */
    protected ?SpySearchRankingProductMetric $productMetricEntity = null;

    /**
     * @return void
     */
    protected function _after(): void
    {
        if ($this->productMetricEntity !== null) {
            $this->productMetricEntity->delete();
        }

        foreach ($this->productAbstractEntities as $productAbstractEntity) {
            $productAbstractEntity->delete();
        }

        if ($this->metricEntity !== null) {
            $this->metricEntity->delete();
        }

        parent::_after();
    }

    /**
     * @return void
     */
    public function testReturnsOnlyProductAbstractsWithoutARowForTheGivenMetric(): void
    {
        // Arrange
        $metricEntity = $this->createTestMetric();
        $withValue = $this->createTestProductAbstract('TEST-GAP-WITH-VALUE');
        $missingA = $this->createTestProductAbstract('TEST-GAP-MISSING-A');
        $missingB = $this->createTestProductAbstract('TEST-GAP-MISSING-B');

        $this->createTestProductMetric($metricEntity, $withValue);

        $productAbstractQuery = SpyProductAbstractQuery::create()
            ->filterByIdProductAbstract([
                $withValue->getIdProductAbstract(),
                $missingA->getIdProductAbstract(),
                $missingB->getIdProductAbstract(),
            ], Criteria::IN);

        // Act
        $gapEntities = (new ProductMetricGapQueryBuilder())
            ->filterMissingMetricValue($productAbstractQuery, $metricEntity->getIdSearchRankingMetric())
            ->find();

        // Assert
        $gapIds = [];

        foreach ($gapEntities as $gapEntity) {
            $gapIds[] = $gapEntity->getIdProductAbstract();
        }

        sort($gapIds);
        $expectedGapIds = [$missingA->getIdProductAbstract(), $missingB->getIdProductAbstract()];
        sort($expectedGapIds);

        $this->assertSame($expectedGapIds, $gapIds, 'Only the two product abstracts with no product-metric row for this metric should be reported as gaps.');
    }

    /**
     * @return void
     */
    public function testReturnsAllRestrictedProductAbstractsWhenNoneHaveAValueForTheMetricAtAll(): void
    {
        // Arrange
        $metricEntity = $this->createTestMetric();
        $missingA = $this->createTestProductAbstract('TEST-GAP-ALL-MISSING-A');
        $missingB = $this->createTestProductAbstract('TEST-GAP-ALL-MISSING-B');

        $productAbstractQuery = SpyProductAbstractQuery::create()
            ->filterByIdProductAbstract([
                $missingA->getIdProductAbstract(),
                $missingB->getIdProductAbstract(),
            ], Criteria::IN);

        // Act
        $gapEntities = (new ProductMetricGapQueryBuilder())
            ->filterMissingMetricValue($productAbstractQuery, $metricEntity->getIdSearchRankingMetric())
            ->find();

        // Assert
        $this->assertCount(2, $gapEntities, 'A brand-new metric with zero product-metric rows at all should report every restricted product abstract as a gap.');
    }

    /**
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric
     */
    protected function createTestMetric(): SpySearchRankingMetric
    {
        $metricEntity = new SpySearchRankingMetric();
        $metricEntity->setName('test_gap_metric_' . uniqid())
            ->setWeight(1.0)
            ->setFormula('x')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->save();

        $this->metricEntity = $metricEntity;

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
        $productAbstractEntity->setSku($sku . '-' . uniqid())
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
    protected function createTestProductMetric(
        SpySearchRankingMetric $metricEntity,
        SpyProductAbstract $productAbstractEntity
    ): SpySearchRankingProductMetric {
        $productMetricEntity = new SpySearchRankingProductMetric();
        $productMetricEntity->setFkSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
            ->setFkProductAbstract($productAbstractEntity->getIdProductAbstract())
            ->setRawValue(5.0)
            ->setNormalizedValue(0.5)
            ->save();

        $this->productMetricEntity = $productMetricEntity;

        return $productMetricEntity;
    }
}
