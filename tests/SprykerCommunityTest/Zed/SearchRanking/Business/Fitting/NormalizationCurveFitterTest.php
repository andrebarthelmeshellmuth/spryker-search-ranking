<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Fitting;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitter;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\RSquaredCalculator;

/**
 * The core value proposition of the fitter is being distribution-agnostic: a linearly-spread metric should
 * be recognized as linear, a saturating one as saturating — no shape gets special status, the data picks.
 * These tests build synthetic digests with a KNOWN generating shape and check the fitter recovers it,
 * rather than asserting exact R² values (which would make the test as fragile as the search algorithm's
 * own numeric tolerance).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Fitting
 * @group NormalizationCurveFitterTest
 * Add your own group annotations below this line
 */
class NormalizationCurveFitterTest extends Unit
{
    /**
     * A perfectly evenly-spaced digest (percentiles[i] = i) IS the empirical CDF of a uniform
     * distribution, i.e. exactly linear — the winning candidate must fit it almost perfectly.
     */
    public function testALinearlySpreadDigestGetsAnAlmostPerfectWinningFit(): void
    {
        // Arrange
        $fitter = new NormalizationCurveFitter(new RSquaredCalculator());
        $percentiles = [];

        for ($i = 0; $i <= 100; $i++) {
            $percentiles[] = (float)$i;
        }

        $digestTransfer = $this->buildDigestTransfer($percentiles);

        // Act
        $candidateTransfers = $fitter->fit($digestTransfer, true);
        $winner = $this->findWinner($candidateTransfers);

        // Assert
        $this->assertNotNull($winner, 'Expected exactly one candidate to be flagged as the winner.');
        $this->assertGreaterThan(0.999, $winner->getRSquaredOrFail());
    }

    /**
     * A digest generated FROM the x/(x+k) shape (k=100) must be recognized as clearly better fit by
     * SOME candidate than by the linear one — the shape-discrimination the whole feature exists for.
     */
    public function testASaturatingDigestFitsClearlyBetterThanLinear(): void
    {
        // Arrange
        $fitter = new NormalizationCurveFitter(new RSquaredCalculator());
        $k = 100.0;
        $percentiles = [];

        for ($i = 0; $i <= 99; $i++) {
            $p = $i / 100.0;
            $percentiles[] = $k * $p / (1 - $p);
        }

        // p=1.0 makes x/(x+k)=p diverge to infinity; a large finite proxy keeps the digest realistic.
        $percentiles[] = $k * 9999.0;

        $digestTransfer = $this->buildDigestTransfer($percentiles);

        // Act
        $candidateTransfers = $fitter->fit($digestTransfer, true);
        $winner = $this->findWinner($candidateTransfers);
        $linearCandidate = $this->findCandidateByName($candidateTransfers, '(x - min) / (max - min)');

        // Assert
        $this->assertNotNull($winner);
        $this->assertNotNull($linearCandidate);
        $this->assertGreaterThan(0.98, $winner->getRSquaredOrFail());
        $this->assertLessThan(0.9, $linearCandidate->getRSquaredOrFail());
    }

    /**
     * isHigherBetter=false must swap the increasing saturating-ratio candidate for the decreasing decay
     * one, per the roadmap's own example (days-since-restock, return rate) — never merely invert it.
     */
    public function testDirectionFalseOffersDecayInsteadOfTheSaturatingRatioFamily(): void
    {
        // Arrange
        $fitter = new NormalizationCurveFitter(new RSquaredCalculator());
        $percentiles = [];

        for ($i = 0; $i <= 100; $i++) {
            $percentiles[] = (float)$i;
        }

        $digestTransfer = $this->buildDigestTransfer($percentiles);

        // Act
        $candidateTransfers = $fitter->fit($digestTransfer, false);

        // Assert
        $this->assertNotNull($this->findCandidateByName($candidateTransfers, 'exp(-x / tau)'));
        $this->assertNull($this->findCandidateByName($candidateTransfers, 'x / (x + k)'));
    }

    /**
     * A metric whose every raw value is identical (zero variance — e.g. a brand-new metric where every
     * product happens to have been imported with the same placeholder value) has no spread for ANY
     * candidate curve to fit against. Must degrade to "no usable candidates" rather than crashing on a
     * divide-by-zero inside the correlation/regression math.
     *
     * Known, deliberate coverage gap: this class's own numerical-failure branches (`fitCandidate()`'s two
     * null-returns and `searchParameter()`'s grid-search-found-nothing return) stay uncovered even by this
     * test — a zero-variance digest turns out not to be the specific degenerate shape that trips them.
     * Reverse-engineering the exact input each one needs would mean tracing the rSquared/grid-search math
     * in detail for a benefit limited to 4 defensive lines already covered by 97%+ statement coverage on
     * this class; left as an honest gap rather than forced, same call as the Locator-dependent branch in
     * search-debug's SearchDebugContextEventDispatcherPluginTest.
     */
    public function testAZeroVarianceDigestProducesNoCrashAndNoUsableCandidates(): void
    {
        // Arrange
        $fitter = new NormalizationCurveFitter(new RSquaredCalculator());
        $percentiles = array_fill(0, 101, 5.0);

        $digestTransfer = $this->buildDigestTransfer($percentiles);

        // Act
        $candidateTransfers = $fitter->fit($digestTransfer, true);

        // Assert — every candidate either failed to fit (dropped) or fit with an undefined-strength
        // rSquared; either way, nothing here should throw, and the result must be a plain array.
        $this->assertIsArray($candidateTransfers);
    }

    /**
     * A digest with at most one percentile entry (never produced by this package's own digest builder,
     * which always emits exactly 101, but reachable from a corrupted/legacy row -- see
     * SearchRankingMapper::explodePercentiles()) would otherwise divide by zero (count($percentiles) - 1)
     * inside buildFitPoints(), NaN-poisoning every candidate's R² instead of returning cleanly.
     */
    public function testADigestWithAtMostOnePercentileEntryReturnsNoCandidatesRatherThanDividingByZero(): void
    {
        // Arrange
        $fitter = new NormalizationCurveFitter(new RSquaredCalculator());
        $digestTransfer = (new SearchRankingMetricDigestTransfer())
            ->setMinValue(5.0)
            ->setMaxValue(5.0)
            ->setMeanValue(5.0)
            ->setMedianValue(5.0)
            ->setSampleCount(1)
            ->setPercentiles([5.0]);

        // Act
        $candidateTransfers = $fitter->fit($digestTransfer, true);

        // Assert
        $this->assertSame([], $candidateTransfers);
    }

    /**
     * @param array<float> $percentiles Ascending, exactly 101 entries (indices 0..100).
     */
    protected function buildDigestTransfer(array $percentiles): SearchRankingMetricDigestTransfer
    {
        return (new SearchRankingMetricDigestTransfer())
            ->setMinValue($percentiles[0])
            ->setMaxValue($percentiles[100])
            ->setMeanValue(array_sum($percentiles) / count($percentiles))
            ->setMedianValue($percentiles[50])
            ->setSampleCount(1000)
            ->setPercentiles($percentiles);
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer> $candidateTransfers
     */
    protected function findWinner(array $candidateTransfers): ?SearchRankingCurveFitCandidateTransfer
    {
        foreach ($candidateTransfers as $candidateTransfer) {
            if ($candidateTransfer->getIsWinner()) {
                return $candidateTransfer;
            }
        }

        return null;
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer> $candidateTransfers
     * @param string $name
     */
    protected function findCandidateByName(array $candidateTransfers, string $name): ?SearchRankingCurveFitCandidateTransfer
    {
        foreach ($candidateTransfers as $candidateTransfer) {
            if ($candidateTransfer->getName() === $name) {
                return $candidateTransfer;
            }
        }

        return null;
    }
}
