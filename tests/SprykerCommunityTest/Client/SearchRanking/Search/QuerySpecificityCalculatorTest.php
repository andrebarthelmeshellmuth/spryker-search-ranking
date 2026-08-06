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
    public function testNoTermsReturnsZero(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $this->assertSame(0.0, $calculator->calculateRawSpecificity([], 0.7));
    }

    public function testASingleTermReturnsItsOwnIdfRegardlessOfBlendWeight(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $this->assertSame(6.28, $calculator->calculateRawSpecificity(['m11480' => 6.28], 0.7));
        $this->assertSame(6.28, $calculator->calculateRawSpecificity(['m11480' => 6.28], 0.0));
        $this->assertSame(6.28, $calculator->calculateRawSpecificity(['m11480' => 6.28], 1.0));
    }

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
     */
    public function testBlendWeightOfZeroIsDominatedByTheSmallestValue(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $rawSpecificity = $calculator->calculateRawSpecificity(['sku' => 6.0, 'common' => 0.0], 0.0);

        $this->assertSame(0.0, $rawSpecificity, 'A 0.0 idf term is the correct harmonic-mean limiting case, not an error to guard against.');
    }

    public function testNormalizeMapsRawSpecificityIntoZeroToOneRange(): void
    {
        $calculator = new QuerySpecificityCalculator();

        // At raw == saturationPoint, normalized must be exactly 0.5 — the defining property of the
        // saturation point, mirroring relevanceSaturationPoint's own x/(x+k) shape.
        $this->assertSame(0.5, $calculator->normalize(3.0, 3.0));
        $this->assertGreaterThan(0.5, $calculator->normalize(6.0, 3.0));
        $this->assertLessThan(0.5, $calculator->normalize(1.0, 3.0));
    }

    public function testNormalizeOfZeroOrNegativeRawSpecificityReturnsZero(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $this->assertSame(0.0, $calculator->normalize(0.0, 3.0));
    }

    /**
     * The defining property of the Hill-equation generalization `raw^p / (raw^p + k^p)`: at raw == k,
     * `k^p / (k^p + k^p)` is always exactly 0.5 for any p > 0 — the pivot itself never moves, only the
     * steepness of the transition around it does. Proves this holds for a real spread of exponents, not
     * just the default p=1.
     */
    public function testNormalizeAtTheSaturationPointIsAlwaysOneHalfRegardlessOfCurveExponent(): void
    {
        $calculator = new QuerySpecificityCalculator();

        foreach ([0.1, 0.5, 1.0, 2.0, 5.0] as $curveExponent) {
            $this->assertEqualsWithDelta(0.5, $calculator->normalize(3.0, 3.0, $curveExponent), 1.0E-9, "p={$curveExponent}");
        }
    }

    /**
     * A curve exponent above 1.0 must sharpen the transition -- push a raw specificity already above the
     * saturation point FURTHER above 0.5 than the original (p=1) curve would, and a raw specificity below
     * the saturation point further below 0.5. This is the entire point of the exponent: reshaping the
     * curve around a fixed pivot, not just rescaling it.
     */
    public function testNormalizeWithCurveExponentAboveOneSharpensTheTransitionAroundThePivot(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $aboveDefault = $calculator->normalize(6.0, 3.0, 1.0);
        $aboveSharpened = $calculator->normalize(6.0, 3.0, 2.0);
        $belowDefault = $calculator->normalize(1.0, 3.0, 1.0);
        $belowSharpened = $calculator->normalize(1.0, 3.0, 2.0);

        $this->assertGreaterThan($aboveDefault, $aboveSharpened, 'A raw value above k must land further above 0.5 as p increases.');
        $this->assertLessThan($belowDefault, $belowSharpened, 'A raw value below k must land further below 0.5 as p increases.');
    }

    public function testNormalizeWithCurveExponentOfOneMatchesTheOriginalUnshapedFormula(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $this->assertSame($calculator->normalize(6.0, 3.0), $calculator->normalize(6.0, 3.0, 1.0));
    }

    public function testNormalizeOfZeroOrNegativeRawSpecificityReturnsZeroRegardlessOfCurveExponent(): void
    {
        $calculator = new QuerySpecificityCalculator();

        $this->assertSame(0.0, $calculator->normalize(0.0, 3.0, 2.5));
        $this->assertSame(0.0, $calculator->normalize(-1.0, 3.0, 2.5));
    }
}
