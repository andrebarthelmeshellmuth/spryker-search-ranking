<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Persistence\Propel\Mapper;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer;
use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingCalibration;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingCalibrationSearchTerm;
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

    /**
     * @return void
     */
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
     * @return void
     */
    public function testMapsMetricHistoryTransferFieldsOntoTheEntity(): void
    {
        // Arrange
        $historyTransfer = (new SearchRankingMetricHistoryTransfer())
            ->setFkSearchRankingMetric(7)
            ->setMetricName('top_seller')
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
     *
     * @return void
     */
    public function testMapsAnEmptyPercentilesArrayToANullColumnRatherThanAnEmptyString(): void
    {
        // Arrange
        $historyTransfer = (new SearchRankingMetricHistoryTransfer())
            ->setFkSearchRankingMetric(7)
            ->setMetricName('brand_new_metric')
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

    /**
     * @return void
     */
    public function testMapsCalibrationEntityFieldsOntoTheTransfer(): void
    {
        // Arrange
        $calibrationEntity = new SpySearchRankingCalibration();
        $calibrationEntity->setIdSearchRankingCalibration(4);
        $calibrationEntity->setRelevantProductCount(20);
        $calibrationEntity->setStoreName('DE');
        $calibrationEntity->setLocaleName('de_DE');
        $calibrationEntity->setStatus('finished');
        $calibrationEntity->setComputedK(1.2);
        $calibrationEntity->setScoreMin(1.0);
        $calibrationEntity->setScoreMax(9.0);
        $calibrationEntity->setScoreMean(5.0);
        $calibrationEntity->setScoreMedian(5.0);
        $calibrationEntity->setScoreP25(3.0);
        $calibrationEntity->setScoreP75(7.0);
        $calibrationEntity->setSampleCount(20);
        $calibrationEntity->setCalculatedAt('2026-01-15 10:00:00');
        $calibrationEntity->setCreatedAt('2026-01-15 09:00:00');

        // Act
        $calibrationTransfer = (new SearchRankingMapper())->mapCalibrationEntityToTransfer(
            $calibrationEntity,
            new SearchRankingCalibrationTransfer(),
        );

        // Assert
        $this->assertSame(4, $calibrationTransfer->getIdSearchRankingCalibration());
        $this->assertSame(20, $calibrationTransfer->getRelevantProductCount());
        $this->assertSame('DE', $calibrationTransfer->getStoreName());
        $this->assertSame('de_DE', $calibrationTransfer->getLocaleName());
        $this->assertSame('finished', $calibrationTransfer->getStatus());
        $this->assertSame(1.2, $calibrationTransfer->getComputedK());
        $this->assertSame(1.0, $calibrationTransfer->getScoreMin());
        $this->assertSame(9.0, $calibrationTransfer->getScoreMax());
        $this->assertSame(5.0, $calibrationTransfer->getScoreMean());
        $this->assertSame(3.0, $calibrationTransfer->getScoreP25());
        $this->assertSame(7.0, $calibrationTransfer->getScoreP75());
        $this->assertSame(20, $calibrationTransfer->getSampleCount());
        $this->assertStringStartsWith('2026-01-15T10:00:00', (string)$calibrationTransfer->getCalculatedAt());
    }

    /**
     * `calculatedAt`/`createdAt` are nullable (e.g. a calibration that hasn't finished running yet) — the
     * nullsafe `?->format()` call must not throw.
     *
     * @return void
     */
    public function testMapsCalibrationEntityWithNoTimestampsToNullDates(): void
    {
        // Arrange
        $calibrationEntity = new SpySearchRankingCalibration();
        $calibrationEntity->setStoreName('DE');
        $calibrationEntity->setLocaleName('de_DE');
        $calibrationEntity->setStatus('running');
        $calibrationEntity->setSampleCount(0);

        // Act
        $calibrationTransfer = (new SearchRankingMapper())->mapCalibrationEntityToTransfer(
            $calibrationEntity,
            new SearchRankingCalibrationTransfer(),
        );

        // Assert
        $this->assertNull($calibrationTransfer->getCalculatedAt());
        $this->assertNull($calibrationTransfer->getCreatedAt());
    }

    /**
     * @return void
     */
    public function testMapsCalibrationSearchTermEntityFieldsOntoTheTransferIncludingExplodedScores(): void
    {
        // Arrange
        $searchTermEntity = new SpySearchRankingCalibrationSearchTerm();
        $searchTermEntity->setIdSearchRankingCalibrationSearchTerm(9);
        $searchTermEntity->setFkSearchRankingCalibration(4);
        $searchTermEntity->setSearchTerm('cable tie');
        $searchTermEntity->setProductsFound(12);
        $searchTermEntity->setScores('1.5,2.5,3.5');

        // Act
        $searchTermTransfer = (new SearchRankingMapper())->mapCalibrationSearchTermEntityToTransfer(
            $searchTermEntity,
            new SearchRankingCalibrationSearchTermTransfer(),
        );

        // Assert
        $this->assertSame(9, $searchTermTransfer->getIdSearchRankingCalibrationSearchTerm());
        $this->assertSame(4, $searchTermTransfer->getFkSearchRankingCalibration());
        $this->assertSame('cable tie', $searchTermTransfer->getSearchTerm());
        $this->assertSame(12, $searchTermTransfer->getProductsFound());
        $this->assertSame([1.5, 2.5, 3.5], $searchTermTransfer->getScores());
    }

    /**
     * A search term with no scores yet must map to an empty array rather than `[0.0]` (which is what a
     * naive `explode(',', '')` would produce).
     *
     * @return void
     */
    public function testMapsACalibrationSearchTermWithNoScoresToAnEmptyArray(): void
    {
        // Arrange
        $searchTermEntity = new SpySearchRankingCalibrationSearchTerm();
        $searchTermEntity->setSearchTerm('no results yet');
        $searchTermEntity->setProductsFound(0);
        $searchTermEntity->setScores(null);

        // Act
        $searchTermTransfer = (new SearchRankingMapper())->mapCalibrationSearchTermEntityToTransfer(
            $searchTermEntity,
            new SearchRankingCalibrationSearchTermTransfer(),
        );

        // Assert
        $this->assertSame([], $searchTermTransfer->getScores());
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testMapsMetricDigestTransferFieldsOntoTheEntity(): void
    {
        // Arrange
        $digestTransfer = (new SearchRankingMetricDigestTransfer())
            ->setFkSearchRankingMetric(7)
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

    /**
     * @return void
     */
    public function testImplodeScoresJoinsScoresWithACommaSeparator(): void
    {
        // Act
        $scores = (new SearchRankingMapper())->implodeScores([1.5, 2.5, 3.5]);

        // Assert
        $this->assertSame('1.5,2.5,3.5', $scores);
    }

    /**
     * Mirrors `mapMetricHistoryTransferToEntity()`'s empty-percentiles handling — an empty scores array
     * must become a genuine NULL, not an empty string, so "no scores recorded" stays distinguishable from
     * a calibration search term that scored everything at zero.
     *
     * @return void
     */
    public function testImplodeScoresReturnsNullForAnEmptyArray(): void
    {
        // Act
        $scores = (new SearchRankingMapper())->implodeScores([]);

        // Assert
        $this->assertNull($scores);
    }
}
