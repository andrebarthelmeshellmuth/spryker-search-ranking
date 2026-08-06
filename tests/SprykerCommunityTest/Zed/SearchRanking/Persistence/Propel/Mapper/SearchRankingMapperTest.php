<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Persistence\Propel\Mapper;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory;
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
     * Since Phase 2 of the store-scoped-formula migration (see project memory): formula/isActive/shape
     * are genuinely never read here anymore — they live on `spy_search_ranking_metric_store_config`
     * instead, mapped separately by {@see \SprykerCommunity\Zed\SearchRanking\Persistence\Propel\Mapper\SearchRankingMapper::mapMetricStoreConfigEntityToTransfer()}
     * (no dedicated unit test of its own yet — a real, separate gap, not one this fix attempts to close).
     * This only asserts the fields the entity mapper still owns: id/name/isHigherBetter.
     */
    public function testMapsMetricEntityFieldsOntoTheTransfer(): void
    {
        // Arrange
        $metricEntity = new SpySearchRankingMetric();
        $metricEntity->setIdSearchRankingMetric(7);
        $metricEntity->setName('top_seller');
        $metricEntity->setIsHigherBetter(true);

        // Act
        $metricTransfer = (new SearchRankingMapper())->mapMetricEntityToTransfer($metricEntity, new SearchRankingMetricTransfer());

        // Assert
        $this->assertSame(7, $metricTransfer->getIdSearchRankingMetric());
        $this->assertSame('top_seller', $metricTransfer->getName());
        $this->assertTrue($metricTransfer->getIsHigherBetter());
    }

    /**
     * Since Phase 2 of the store-scoped-formula migration (see project memory): formula/isActive/shape
     * are genuinely never written here anymore — see the sibling test above.
     */
    public function testMapsMetricTransferFieldsOntoTheEntity(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setName('pdp_impressions')
            ->setIsHigherBetter(false);

        // Act
        $metricEntity = (new SearchRankingMapper())->mapMetricTransferToEntity($metricTransfer, new SpySearchRankingMetric());

        // Assert
        $this->assertSame('pdp_impressions', $metricEntity->getName());
        $this->assertFalse($metricEntity->getIsHigherBetter());
    }

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

    public function testMapsMetricHistoryEntityFieldsOntoTheTransferIncludingTheDigestSnapshot(): void
    {
        // Arrange
        $historyEntity = new SpySearchRankingMetricHistory();
        $historyEntity->setIdSearchRankingMetricHistory(3);
        $historyEntity->setFkSearchRankingMetric(7);
        $historyEntity->setMetricName('top_seller');
        $historyEntity->setWeight(0.5);
        $historyEntity->setFormula('atan(x / 100) / (pi() / 2)');
        $historyEntity->setIsActive(true);
        $historyEntity->setIsHigherBetter(true);
        $historyEntity->setMinValue(0.0);
        $historyEntity->setMaxValue(100.0);
        $historyEntity->setMeanValue(50.0);
        $historyEntity->setMedianValue(50.0);
        $historyEntity->setSampleCount(3);
        $historyEntity->setPercentiles('0,50,100');
        $historyEntity->setFitRSquared(0.75);
        $historyEntity->setIsChange(true);

        // Act
        $historyTransfer = (new SearchRankingMapper())->mapMetricHistoryEntityToTransfer($historyEntity, new SearchRankingMetricHistoryTransfer());

        // Assert
        $this->assertSame(3, $historyTransfer->getIdSearchRankingMetricHistory());
        $this->assertSame(7, $historyTransfer->getFkSearchRankingMetric());
        $this->assertSame('top_seller', $historyTransfer->getMetricName());
        $this->assertSame('atan(x / 100) / (pi() / 2)', $historyTransfer->getFormula());
        $this->assertSame([0.0, 50.0, 100.0], $historyTransfer->getPercentiles());
        $this->assertSame(0.75, $historyTransfer->getFitRSquared());
        $this->assertTrue($historyTransfer->getIsChange());
    }

    /**
     * A brand-new metric with no digest yet at snapshot time has a NULL percentiles column (the history
     * table's percentiles is nullable, unlike the live digest table's) — must decode to an empty array,
     * not a one-element `[0.0]` array a naive `explode(',', '')` would produce.
     */
    public function testMapsAHistoryEntityWithNoDigestYetToEmptyPercentiles(): void
    {
        // Arrange
        $historyEntity = new SpySearchRankingMetricHistory();
        $historyEntity->setFkSearchRankingMetric(11);
        $historyEntity->setMetricName('brand_new_metric');
        $historyEntity->setWeight(0.1);
        $historyEntity->setFormula('x');
        $historyEntity->setIsActive(true);
        $historyEntity->setIsHigherBetter(true);
        $historyEntity->setIsChange(true);

        // Act
        $historyTransfer = (new SearchRankingMapper())->mapMetricHistoryEntityToTransfer($historyEntity, new SearchRankingMetricHistoryTransfer());

        // Assert
        $this->assertSame([], $historyTransfer->getPercentiles());
    }

    public function testMapsMetricHistoryTransferFieldsOntoTheEntity(): void
    {
        // Arrange
        $historyTransfer = (new SearchRankingMetricHistoryTransfer())
            ->setFkSearchRankingMetric(7)
            ->setMetricName('top_seller')
            ->setStoreName('DE')
            ->setLocaleName('de_DE')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setMinValue(0.0)
            ->setMaxValue(100.0)
            ->setMeanValue(50.0)
            ->setMedianValue(50.0)
            ->setSampleCount(3)
            ->setPercentiles([0.0, 50.0, 100.0])
            ->setFitRSquared(0.9)
            ->setIsChange(true);

        // Act
        $historyEntity = (new SearchRankingMapper())->mapMetricHistoryTransferToEntity($historyTransfer, new SpySearchRankingMetricHistory());

        // Assert
        $this->assertSame(7, $historyEntity->getFkSearchRankingMetric());
        $this->assertSame('top_seller', $historyEntity->getMetricName());
        $this->assertSame('x / max', $historyEntity->getFormula());
        $this->assertSame('0,50,100', $historyEntity->getPercentiles());
        $this->assertSame(0.9, $historyEntity->getFitRSquared());
        $this->assertTrue($historyEntity->getIsChange());
    }

    /**
     * No digest existed yet at snapshot time (e.g. a brand-new metric) — the transfer's empty percentiles
     * array must map to a genuinely NULL column, not an empty string, so "no digest" stays distinguishable
     * from "a digest that happened to be empty".
     */
    public function testMapsAnEmptyPercentilesArrayToANullColumnRatherThanAnEmptyString(): void
    {
        // Arrange
        $historyTransfer = (new SearchRankingMetricHistoryTransfer())
            ->setFkSearchRankingMetric(7)
            ->setMetricName('brand_new_metric')
            ->setStoreName('DE')
            ->setLocaleName('de_DE')
            ->setWeight(0.1)
            ->setFormula('x')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->setIsChange(true);

        // Act
        $historyEntity = (new SearchRankingMapper())->mapMetricHistoryTransferToEntity($historyTransfer, new SpySearchRankingMetricHistory());

        // Assert
        $this->assertNull($historyEntity->getPercentiles());
    }

    public function testMapsMetricDigestEntityFieldsOntoTheTransferIncludingExplodedPercentiles(): void
    {
        // Arrange
        $digestEntity = new SpySearchRankingMetricDigest();
        $digestEntity->setIdSearchRankingMetricDigest(2);
        $digestEntity->setFkSearchRankingMetric(7);
        $digestEntity->setMinValue(0.0);
        $digestEntity->setMaxValue(100.0);
        $digestEntity->setMeanValue(50.0);
        $digestEntity->setMedianValue(50.0);
        $digestEntity->setSampleCount(3);
        $digestEntity->setPercentiles('0,50,100');

        // Act
        $digestTransfer = (new SearchRankingMapper())->mapMetricDigestEntityToTransfer($digestEntity, new SearchRankingMetricDigestTransfer());

        // Assert
        $this->assertSame(2, $digestTransfer->getIdSearchRankingMetricDigest());
        $this->assertSame(7, $digestTransfer->getFkSearchRankingMetric());
        $this->assertSame(0.0, $digestTransfer->getMinValue());
        $this->assertSame(100.0, $digestTransfer->getMaxValue());
        $this->assertSame(3, $digestTransfer->getSampleCount());
        $this->assertSame([0.0, 50.0, 100.0], $digestTransfer->getPercentiles());
    }

    public function testMapsMetricDigestTransferFieldsOntoTheEntity(): void
    {
        // Arrange
        $digestTransfer = (new SearchRankingMetricDigestTransfer())
            ->setFkSearchRankingMetric(7)
            ->setStoreName('DE')
            ->setLocaleName('de_DE')
            ->setMinValue(0.0)
            ->setMaxValue(100.0)
            ->setMeanValue(50.0)
            ->setMedianValue(50.0)
            ->setSampleCount(3)
            ->setPercentiles([0.0, 50.0, 100.0]);

        // Act
        $digestEntity = (new SearchRankingMapper())->mapMetricDigestTransferToEntity($digestTransfer, new SpySearchRankingMetricDigest());

        // Assert
        $this->assertSame(7, $digestEntity->getFkSearchRankingMetric());
        $this->assertSame(0.0, $digestEntity->getMinValue());
        $this->assertSame(100.0, $digestEntity->getMaxValue());
        $this->assertSame(3, $digestEntity->getSampleCount());
        $this->assertSame('0,50,100', $digestEntity->getPercentiles());
    }
}
