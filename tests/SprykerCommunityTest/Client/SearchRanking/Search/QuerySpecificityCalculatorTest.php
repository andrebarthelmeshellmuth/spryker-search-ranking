<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculator;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group QuerySpecificityCalculatorTest
 */
class QuerySpecificityCalculatorTest extends Unit
{
    /**
     * @return void
     */
    public function testNoTermsReturnsZero(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $this->assertSame(0.0, $calculator->calculateRawSpecificity([], 0.7));
    }

    /**
     * @return void
     */
    public function testASingleTermReturnsItsOwnIdfRegardlessOfBlendWeight(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $this->assertSame(6.28, $calculator->calculateRawSpecificity(['m11480' => 6.28], 0.7));
        $this->assertSame(6.28, $calculator->calculateRawSpecificity(['m11480' => 6.28], 0.0));
        $this->assertSame(6.28, $calculator->calculateRawSpecificity(['m11480' => 6.28], 1.0));
    }

    /**
     * @return void
     */
    public function testBlendsMaxAndHarmonicMeanByTheGivenWeight(): void
    {
        $calculator = new QuerySpecificityCalculator();

        // max = 6.0, harmonicMean = 2 / (1/6.0 + 1/2.0) = 3.0
        $rawSpecificity = $calculator->calculateRawSpecificity(['sku' => 6.0, 'chair' => 2.0], 0.7);

        $this->assertEqualsWithDelta((0.7 * 6.0) + (0.3 * 3.0), $rawSpecificity, 1.0E-9);
    }

    /**
     * A blend weight of 1.0 collapses to pure max — the harmonic mean term should have no influence at
     * all, even with a very small other value that would otherwise drag the harmonic mean toward zero.
     *
     * @return void
     */
    public function testBlendWeightOfOneIgnoresTheHarmonicMeanEntirely(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $rawSpecificity = $calculator->calculateRawSpecificity(['sku' => 6.0, 'common' => 0.001], 1.0);

        $this->assertSame(6.0, $rawSpecificity);
    }

    /**
     * A blend weight of 0.0 collapses to pure harmonic mean — a single common (near-zero idf) term should
     * drag the whole result toward zero, even alongside a very rare one.
     *
     * @return void
     */
    public function testBlendWeightOfZeroIsDominatedByTheSmallestValue(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $rawSpecificity = $calculator->calculateRawSpecificity(['sku' => 6.0, 'common' => 0.0], 0.0);

        $this->assertSame(0.0, $rawSpecificity, 'A 0.0 idf term is the correct harmonic-mean limiting case, not an error to guard against.');
    }

    /**
     * @return void
     */
    public function testNormalizeMapsRawSpecificityIntoZeroToOneRange(): void
    {
        $calculator = new QuerySpecificityCalculator();

        // At raw == saturationPoint, normalized must be exactly 0.5 — the defining property of the
        // saturation point, mirroring relevanceSaturationPoint's own x/(x+k) shape.
        $this->assertSame(0.5, $calculator->normalize(3.0, 3.0));
        $this->assertGreaterThan(0.5, $calculator->normalize(6.0, 3.0));
        $this->assertLessThan(0.5, $calculator->normalize(1.0, 3.0));
    }

    /**
     * @return void
     */
    public function testNormalizeOfZeroOrNegativeRawSpecificityReturnsZero(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $this->assertSame(0.0, $calculator->normalize(0.0, 3.0));
    }
}
