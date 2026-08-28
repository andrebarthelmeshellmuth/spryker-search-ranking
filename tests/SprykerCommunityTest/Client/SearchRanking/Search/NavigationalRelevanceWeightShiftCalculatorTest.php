<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Client\SearchRanking\Search\NavigationalRelevanceWeightShiftCalculator;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group NavigationalRelevanceWeightShiftCalculatorTest
 * @group Portable
 */
class NavigationalRelevanceWeightShiftCalculatorTest extends Unit
{
    /**
     * @var float
     */
    protected const BASE_RELEVANCE_WEIGHT = 0.5;

    public function testIsANoOpWhenNoSignalIsDetectedRegardlessOfConfiguredShifts(): void
    {
        $result = $this->createCalculator()->calculateEffectiveRelevanceWeight(
            static::BASE_RELEVANCE_WEIGHT,
            $this->createConfigurationTransfer(0.2, -0.2),
            $this->createQueryContextTransfer(null, null),
        );

        $this->assertSame(static::BASE_RELEVANCE_WEIGHT, $result);
    }

    public function testIsANoOpWhenBothShiftsAreAtTheirZeroDefaultEvenWithBothSignalsDetected(): void
    {
        $result = $this->createCalculator()->calculateEffectiveRelevanceWeight(
            static::BASE_RELEVANCE_WEIGHT,
            $this->createConfigurationTransfer(0.0, 0.0),
            $this->createQueryContextTransfer('Topstar', 'Chairs'),
        );

        $this->assertSame(static::BASE_RELEVANCE_WEIGHT, $result);
    }

    public function testAppliesOnlyTheBrandShiftWhenOnlyABrandIsDetected(): void
    {
        $result = $this->createCalculator()->calculateEffectiveRelevanceWeight(
            static::BASE_RELEVANCE_WEIGHT,
            $this->createConfigurationTransfer(0.2, -0.3),
            $this->createQueryContextTransfer('Topstar', null),
        );

        $this->assertSame(0.7, $result);
    }

    public function testAppliesOnlyTheCategoryShiftWhenOnlyACategoryIsDetected(): void
    {
        $result = $this->createCalculator()->calculateEffectiveRelevanceWeight(
            static::BASE_RELEVANCE_WEIGHT,
            $this->createConfigurationTransfer(0.2, -0.3),
            $this->createQueryContextTransfer(null, 'Chairs'),
        );

        $this->assertSame(0.2, $result);
    }

    public function testComposesBothShiftsWhenBothSignalsAreDetected(): void
    {
        $result = $this->createCalculator()->calculateEffectiveRelevanceWeight(
            static::BASE_RELEVANCE_WEIGHT,
            $this->createConfigurationTransfer(0.2, 0.1),
            $this->createQueryContextTransfer('Topstar', 'Chairs'),
        );

        $this->assertSame(0.8, $result);
    }

    public function testClampsTheComposedShiftAtTheUpperBoundInsteadOfClampingEachTermSeparately(): void
    {
        $result = $this->createCalculator()->calculateEffectiveRelevanceWeight(
            0.9,
            $this->createConfigurationTransfer(0.5, 0.5),
            $this->createQueryContextTransfer('Topstar', 'Chairs'),
        );

        $this->assertSame(1.0, $result);
    }

    public function testClampsTheComposedShiftAtTheLowerBoundInsteadOfClampingEachTermSeparately(): void
    {
        $result = $this->createCalculator()->calculateEffectiveRelevanceWeight(
            0.1,
            $this->createConfigurationTransfer(-0.5, -0.5),
            $this->createQueryContextTransfer('Topstar', 'Chairs'),
        );

        $this->assertSame(0.0, $result);
    }

    /**
     * Proves this composes on top of an arbitrary base — e.g. the relevanceWeight specificity weighting
     * already produced — not just the plain configured relevanceWeight.
     */
    public function testComposesOnTopOfANonDefaultBaseRelevanceWeight(): void
    {
        $result = $this->createCalculator()->calculateEffectiveRelevanceWeight(
            0.65,
            $this->createConfigurationTransfer(0.1, 0.0),
            $this->createQueryContextTransfer('Topstar', null),
        );

        $this->assertSame(0.75, $result);
    }

    protected function createCalculator(): NavigationalRelevanceWeightShiftCalculator
    {
        return new NavigationalRelevanceWeightShiftCalculator();
    }

    protected function createConfigurationTransfer(
        float $brandMatchRelevanceWeightShift,
        float $categoryMatchRelevanceWeightShift,
    ): SearchRankingConfigurationStorageTransfer {
        return (new SearchRankingConfigurationStorageTransfer())
            ->setBrandMatchRelevanceWeightShift($brandMatchRelevanceWeightShift)
            ->setCategoryMatchRelevanceWeightShift($categoryMatchRelevanceWeightShift);
    }

    protected function createQueryContextTransfer(?string $detectedBrand, ?string $detectedCategory): SearchRankingQueryContextTransfer
    {
        return (new SearchRankingQueryContextTransfer())
            ->setDetectedBrand($detectedBrand)
            ->setDetectedCategory($detectedCategory);
    }
}
