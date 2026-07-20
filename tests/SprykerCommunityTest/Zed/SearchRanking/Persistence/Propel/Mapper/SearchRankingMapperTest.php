<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Persistence\Propel\Mapper;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric;
use SprykerCommunity\Zed\SearchRanking\Persistence\Propel\Mapper\SearchRankingMapper;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Persistence
 * @group Propel
 * @group Mapper
 * @group SearchRankingMapperTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class SearchRankingMapperTest extends Unit
{
    /**
     * @return void
     */
    public function testMapsMetricEntityFieldsOntoTheTransfer(): void
    {
        // Arrange
        $metricEntity = new SpySearchRankingMetric();
        $metricEntity->setIdSearchRankingMetric(7);
        $metricEntity->setName('top_seller');
        $metricEntity->setWeight(0.5);
        $metricEntity->setFormula('x / max');
        $metricEntity->setIsActive(true);

        // Act
        $metricTransfer = (new SearchRankingMapper())->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());

        // Assert
        $this->assertSame(7, $metricTransfer->getIdSearchRankingMetric());
        $this->assertSame('top_seller', $metricTransfer->getName());
        $this->assertSame(0.5, $metricTransfer->getWeight());
        $this->assertSame('x / max', $metricTransfer->getFormula());
        $this->assertTrue($metricTransfer->getIsActive());
    }

    /**
     * @return void
     */
    public function testMapsMetricTransferFieldsOntoTheEntity(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('pdp_impressions')
            ->setWeight(0.3)
            ->setFormula('atan(x / avg) / (pi() / 2)')
            ->setIsActive(false);

        // Act
        $metricEntity = (new SearchRankingMapper())->mapMetricTransferToEntity($metricTransfer, new SpySearchRankingMetric());

        // Assert
        $this->assertSame('pdp_impressions', $metricEntity->getName());
        $this->assertSame(0.3, $metricEntity->getWeight());
        $this->assertSame('atan(x / avg) / (pi() / 2)', $metricEntity->getFormula());
        $this->assertFalse($metricEntity->getIsActive());
    }

    /**
     * A transfer with no `isActive` set (null) must still default the entity to active — mirrors the
     * Propel column default and the "new metrics are active by default" admin expectation.
     *
     * @return void
     */
    public function testDefaultsTheEntityToActiveWhenTheTransferLeavesIsActiveUnset(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('new_metric')
            ->setWeight(0.1)
            ->setFormula('x');

        // Act
        $metricEntity = (new SearchRankingMapper())->mapMetricTransferToEntity($metricTransfer, new SpySearchRankingMetric());

        // Assert
        $this->assertTrue($metricEntity->getIsActive());
    }

    /**
     * @return void
     */
    public function testMapsProductMetricEntityFieldsOntoTheTransfer(): void
    {
        // Arrange
        $productMetricEntity = new SpySearchRankingProductMetric();
        $productMetricEntity->setIdSearchRankingProductMetric(11);
        $productMetricEntity->setFkSearchRankingMetric(7);
        $productMetricEntity->setFkProductAbstract(101);
        $productMetricEntity->setRawValue(250.0);
        $productMetricEntity->setNormalizedValue(0.25);

        // Act
        $productMetricTransfer = (new SearchRankingMapper())->mapProductMetricEntityToTransfer(
            $productMetricEntity,
            new SearchRankingProductMetricTransfer(),
        );

        // Assert
        $this->assertSame(11, $productMetricTransfer->getIdSearchRankingProductMetric());
        $this->assertSame(7, $productMetricTransfer->getFkSearchRankingMetric());
        $this->assertSame(101, $productMetricTransfer->getFkProductAbstract());
        $this->assertSame(250.0, $productMetricTransfer->getRawValue());
        $this->assertSame(0.25, $productMetricTransfer->getNormalizedValue());
    }
}
