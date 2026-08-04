<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculator;
use SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface;
use SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyResult;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator;

/**
 * Uses the REAL `QuerySpecificityCalculator` (already covered exactly by its own test with plain arrays)
 * and only stubs the `QueryTermFrequencyFetcherInterface` IO boundary — proving this class's own
 * orchestration (idf derivation, the shift-around-baseline arithmetic, the configured fallback path), not
 * re-testing the blend/normalize math itself.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group SpecificityWeightCalculatorTest
 */
class SpecificityWeightCalculatorTest extends Unit
{
    /**
     * @var float
     */
    protected const CONFIGURED_RELEVANCE_WEIGHT = 0.5;

    /**
     * @var float
     */
    protected const SHIFT_MAGNITUDE = 0.5;

    /**
     * @return void
     */
    public function testAHighlySpecificQueryShiftsWeightAboveTheConfiguredBaseline(): void
    {
        // idf = ln(1000/2) = 6.21 — far above the saturation point (3.0), so normalized specificity is
        // well above 0.5.
        $weight = $this->createCalculator($this->createFetcherStub(1000, ['m11480' => 2]))
            ->calculateRelevanceWeight('m11480', $this->createConfigurationTransfer());

        $this->assertGreaterThan(
            static::CONFIGURED_RELEVANCE_WEIGHT,
            $weight,
            'A rare, highly specific term should shift weight above the configured baseline, toward text relevance.',
        );
    }

    /**
     * @return void
     */
    public function testAnUnspecificQueryShiftsWeightBelowTheConfiguredBaseline(): void
    {
        // idf = ln(1000/900) = 0.105 — far below the saturation point (3.0), so normalized specificity is
        // well below 0.5.
        $weight = $this->createCalculator($this->createFetcherStub(1000, ['office' => 900, 'chair' => 900]))
            ->calculateRelevanceWeight('office chair', $this->createConfigurationTransfer());

        $this->assertLessThan(
            static::CONFIGURED_RELEVANCE_WEIGHT,
            $weight,
            'Only-common terms should shift weight below the configured baseline, toward business signals.',
        );
    }

    /**
     * @return void
     */
    public function testEmptySearchStringFallsBackToTheConfiguredWeightUnchanged(): void
    {
        $weight = $this->createCalculator($this->createFetcherStub(0, []))
            ->calculateRelevanceWeight('', $this->createConfigurationTransfer());

        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $weight);
    }

    /**
     * A failed/empty probe (docCount 0) must fall back gracefully, not error out or divide by zero.
     *
     * @return void
     */
    public function testAFailedProbeFallsBackToTheConfiguredWeightUnchanged(): void
    {
        $weight = $this->createCalculator($this->createFetcherStub(0, []))
            ->calculateRelevanceWeight('gadget', $this->createConfigurationTransfer());

        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $weight);
    }

    /**
     * A term with zero real corpus evidence must be skipped, not treated as maximally specific — a query
     * consisting ONLY of such terms therefore has nothing to measure, same as an empty search string.
     *
     * @return void
     */
    public function testATermWithZeroDocFrequencyIsSkippedEntirely(): void
    {
        $result = $this->createCalculator($this->createFetcherStub(1000, ['typo' => 0]))
            ->calculateWeightingResult('typo', $this->createConfigurationTransfer());

        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $result->getRelevanceWeight());
        $this->assertSame(0, $result->getQueryTermCount());
    }

    /**
     * @return void
     */
    public function testWeightingResultCarriesConsistentDiagnostics(): void
    {
        $result = $this->createCalculator($this->createFetcherStub(1000, ['m11480' => 2]))
            ->calculateWeightingResult('m11480', $this->createConfigurationTransfer());

        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $result->getConfiguredRelevanceWeight());
        $this->assertSame(1, $result->getQueryTermCount());
        $this->assertGreaterThan(0.5, $result->getNormalizedSpecificity());
        $this->assertGreaterThan(0.0, $result->getShift());
        $this->assertSame(
            max(0.0, min(1.0, static::CONFIGURED_RELEVANCE_WEIGHT + $result->getShift())),
            $result->getRelevanceWeight(),
            'relevanceWeight must be exactly configuredRelevanceWeight + shift (clamped) — the same arithmetic calculateRelevanceWeight() performs.',
        );
    }

    /**
     * @return void
     */
    public function testWeightingResultForAnEmptySearchStringCarriesZeroedDiagnostics(): void
    {
        $result = $this->createCalculator($this->createFetcherStub(0, []))
            ->calculateWeightingResult('', $this->createConfigurationTransfer());

        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $result->getConfiguredRelevanceWeight());
        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $result->getRelevanceWeight());
        $this->assertSame(0.0, $result->getNormalizedSpecificity());
        $this->assertSame(0.0, $result->getShift());
        $this->assertSame(0, $result->getQueryTermCount());
    }

    /**
     * @param int $docCount
     * @param array<string, int> $termDocumentFrequencies
     *
     * @return \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface
     */
    protected function createFetcherStub(int $docCount, array $termDocumentFrequencies): QueryTermFrequencyFetcherInterface
    {
        $fetcher = $this->createMock(QueryTermFrequencyFetcherInterface::class);
        $fetcher->method('fetch')->willReturn(new QueryTermFrequencyResult($docCount, $termDocumentFrequencies));

        return $fetcher;
    }

    /**
     * @param \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface $fetcher
     *
     * @return \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator
     */
    protected function createCalculator(QueryTermFrequencyFetcherInterface $fetcher): SpecificityWeightCalculator
    {
        return new SpecificityWeightCalculator(
            $fetcher,
            new QuerySpecificityCalculator(),
            ['full-text-boosted' => 'standard'],
        );
    }

    /**
     * @return \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer
     */
    protected function createConfigurationTransfer(): SearchRankingConfigurationStorageTransfer
    {
        return (new SearchRankingConfigurationStorageTransfer())
            ->setRelevanceWeight(static::CONFIGURED_RELEVANCE_WEIGHT)
            ->setSpecificityBlendWeight(0.7)
            ->setSpecificitySaturationPoint(3.0)
            ->setSpecificityWeightExponent(1.0)
            ->setSpecificityWeightShiftMagnitude(static::SHIFT_MAGNITUDE);
    }
}
