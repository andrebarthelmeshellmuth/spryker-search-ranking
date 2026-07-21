<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Randomizer;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisherInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizer;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Randomizer
 * @group MetricRandomizerTest
 * Add your own group annotations below this line
 */
class MetricRandomizerTest extends Unit
{
    /**
     * @return void
     */
    public function testReturnsFalseAndDoesNothingWhenTheMetricDoesNotExist(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricByName')->with('random')->willReturn(null);

        $normalizerMock = $this->createMock(ProductMetricNormalizerInterface::class);
        $normalizerMock->expects($this->never())->method('normalizeMetric');

        $publisherMock = $this->createMock(ProductAbstractScorePublisherInterface::class);
        $publisherMock->expects($this->never())->method('publishScoredProductAbstracts');

        $randomizer = new MetricRandomizer($repositoryMock, $normalizerMock, $publisherMock, 'random');

        // Act
        $wasRandomized = $randomizer->randomizeIfActive();

        // Assert
        $this->assertFalse($wasRandomized);
    }

    /**
     * @return void
     */
    public function testReturnsFalseAndDoesNothingWhenTheMetricIsNotActive(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(3)
            ->setName('random')
            ->setIsActive(false);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricByName')->with('random')->willReturn($metricTransfer);

        $normalizerMock = $this->createMock(ProductMetricNormalizerInterface::class);
        $normalizerMock->expects($this->never())->method('normalizeMetric');

        $publisherMock = $this->createMock(ProductAbstractScorePublisherInterface::class);
        $publisherMock->expects($this->never())->method('publishScoredProductAbstracts');

        $randomizer = new MetricRandomizer($repositoryMock, $normalizerMock, $publisherMock, 'random');

        // Act
        $wasRandomized = $randomizer->randomizeIfActive();

        // Assert
        $this->assertFalse($wasRandomized);
    }

    /**
     * @return void
     */
    public function testRenormalizesAndRepublishesWhenTheMetricIsActive(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(3)
            ->setName('random')
            ->setIsActive(true);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findMetricByName')->with('random')->willReturn($metricTransfer);

        $normalizerMock = $this->createMock(ProductMetricNormalizerInterface::class);
        $normalizerMock->expects($this->once())->method('normalizeMetric')->with($metricTransfer);

        $publisherMock = $this->createMock(ProductAbstractScorePublisherInterface::class);
        $publisherMock->expects($this->once())->method('publishScoredProductAbstracts');

        $randomizer = new MetricRandomizer($repositoryMock, $normalizerMock, $publisherMock, 'random');

        // Act
        $wasRandomized = $randomizer->randomizeIfActive();

        // Assert
        $this->assertTrue($wasRandomized);
    }
}
