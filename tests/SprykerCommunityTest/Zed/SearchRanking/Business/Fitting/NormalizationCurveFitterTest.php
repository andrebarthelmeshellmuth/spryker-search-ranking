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
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
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
     * @param array<float> $percentiles Ascending, exactly 101 entries (indices 0..100).
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer
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
     *
     * @return \Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer|null
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
     *
     * @return \Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer|null
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
