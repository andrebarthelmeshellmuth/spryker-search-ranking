<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use Elastica\Query\MatchQuery;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightCalculator;
use SprykerCommunity\Client\SearchRanking\Search\ShannonEntropyCalculator;
use SprykerCommunityTest\Client\SearchRanking\Fixture\TestRelevanceIndexTrait;

/**
 * INTEGRATION TEST — talks to a real Elasticsearch, against a TEST-OWNED index
 * (`TestRelevanceIndexTrait`), never the shop's real page index. Uses a test-only subclass overriding the
 * protected `resolveIndexName()` instead of touching `EntropyWeightCalculator`'s production index
 * resolution — that resolution deliberately always targets the CURRENT store's real page index at
 * runtime, and must stay that way; the override exists only so this test can point the exact same
 * round-trip logic at a disposable index instead.
 *
 * Proves the real end-to-end wiring (index round-trip, `size`/`_source:false` params, score extraction,
 * the entropy calculator actually being invoked, the shift-around-baseline arithmetic, the configured
 * fallback path) — NOT the entropy formula itself, which `ShannonEntropyCalculatorTest` already covers
 * exactly with plain arrays. Assertions here are deliberately RELATIVE to the configured baseline, not
 * tied to precise BM25 internals this test doesn't control.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group EntropyWeightCalculatorTest
 */
class EntropyWeightCalculatorTest extends Unit
{
    use TestRelevanceIndexTrait;

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
    protected function _before(): void
    {
        $this->createTestRelevanceIndex();
    }

    /**
     * @return void
     */
    protected function _after(): void
    {
        $this->deleteTestRelevanceIndex();
    }

    /**
     * @return void
     */
    public function testUniformScoreDistributionShiftsWeightBelowTheConfiguredBaseline(): void
    {
        $this->indexTestRelevanceDocuments([
            $this->createTestRelevanceDocument('doc-1', 'gadget for the home'),
            $this->createTestRelevanceDocument('doc-2', 'gadget for the office'),
            $this->createTestRelevanceDocument('doc-3', 'gadget for the garden'),
            $this->createTestRelevanceDocument('doc-4', 'gadget for the kitchen'),
            $this->createTestRelevanceDocument('doc-5', 'gadget for the workshop'),
        ]);

        $weight = $this->createCalculator()->calculateRelevanceWeight(
            new MatchQuery('full-text', 'gadget'),
            $this->createConfigurationTransfer(),
        );

        $this->assertLessThan(
            static::CONFIGURED_RELEVANCE_WEIGHT,
            $weight,
            'A near-flat score distribution (every doc matches "gadget" once) should shift weight below the configured baseline, toward business signals.',
        );
    }

    /**
     * A single-match query — the real "exact SKU" case entropy weighting is meant to detect — is a
     * deterministic, BM25-internals-independent way to reach `normalizedEntropy = 0` exactly (via
     * `ShannonEntropyCalculator`'s own "fewer than 2 scores" guard): only one document in the corpus
     * contains the queried term at all, so exactly one result comes back.
     *
     * @return void
     */
    public function testASingleMatchingCandidateShiftsWeightAboveTheConfiguredBaseline(): void
    {
        $this->indexTestRelevanceDocuments([
            $this->createTestRelevanceDocument('doc-dominant', 'gadget for the home'),
            $this->createTestRelevanceDocument('doc-2', 'widget for the office'),
            $this->createTestRelevanceDocument('doc-3', 'widget for the garden'),
            $this->createTestRelevanceDocument('doc-4', 'widget for the kitchen'),
            $this->createTestRelevanceDocument('doc-5', 'widget for the workshop'),
        ]);

        $weight = $this->createCalculator()->calculateRelevanceWeight(
            new MatchQuery('full-text', 'gadget'),
            $this->createConfigurationTransfer(),
        );

        $this->assertGreaterThan(
            static::CONFIGURED_RELEVANCE_WEIGHT,
            $weight,
            'A single matching candidate carries no distribution to measure (normalizedEntropy = 0 by definition) — weight should shift above the configured baseline, toward text relevance.',
        );
    }

    /**
     * @return void
     */
    public function testNoHitsFallsBackToTheConfiguredWeightUnchanged(): void
    {
        $this->indexTestRelevanceDocuments([
            $this->createTestRelevanceDocument('doc-1', 'gadget for the home'),
        ]);

        $weight = $this->createCalculator()->calculateRelevanceWeight(
            new MatchQuery('full-text', 'no-such-term-anywhere'),
            $this->createConfigurationTransfer(),
        );

        $this->assertSame(
            static::CONFIGURED_RELEVANCE_WEIGHT,
            $weight,
            'Zero hits means no usable signal — must fall back to the configured weight exactly, not error out.',
        );
    }

    /**
     * `calculateRelevanceWeight()` is now a thin wrapper around `calculateWeightingResult()` — this proves
     * the diagnostics it carries (probe candidate count, normalized entropy, shift) are internally
     * consistent with each other and with the resulting weight, for the same near-flat distribution
     * `testUniformScoreDistributionShiftsWeightBelowTheConfiguredBaseline()` covers.
     *
     * @return void
     */
    public function testWeightingResultCarriesConsistentDiagnosticsForAUniformDistribution(): void
    {
        $this->indexTestRelevanceDocuments([
            $this->createTestRelevanceDocument('doc-1', 'gadget for the home'),
            $this->createTestRelevanceDocument('doc-2', 'gadget for the office'),
            $this->createTestRelevanceDocument('doc-3', 'gadget for the garden'),
            $this->createTestRelevanceDocument('doc-4', 'gadget for the kitchen'),
            $this->createTestRelevanceDocument('doc-5', 'gadget for the workshop'),
        ]);

        $result = $this->createCalculator()->calculateWeightingResult(
            new MatchQuery('full-text', 'gadget'),
            $this->createConfigurationTransfer(),
        );

        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $result->getConfiguredRelevanceWeight());
        $this->assertSame(5, $result->getProbeCandidateCount());
        $this->assertGreaterThan(0.0, $result->getNormalizedEntropy());
        $this->assertLessThan(0.0, $result->getShift(), 'A near-flat distribution shifts weight DOWN, so the shift itself must be negative.');
        $this->assertSame(
            max(0.0, min(1.0, static::CONFIGURED_RELEVANCE_WEIGHT + $result->getShift())),
            $result->getRelevanceWeight(),
            'relevanceWeight must be exactly configuredRelevanceWeight + shift (clamped) — the same arithmetic calculateRelevanceWeight() performs.',
        );
    }

    /**
     * Same relationship as above, for the single-dominant-candidate case
     * (`testASingleMatchingCandidateShiftsWeightAboveTheConfiguredBaseline()`), where normalizedEntropy is
     * `0.0` by `ShannonEntropyCalculator`'s own "fewer than 2 scores" guard.
     *
     * @return void
     */
    public function testWeightingResultCarriesConsistentDiagnosticsForASingleDominantCandidate(): void
    {
        $this->indexTestRelevanceDocuments([
            $this->createTestRelevanceDocument('doc-dominant', 'gadget for the home'),
            $this->createTestRelevanceDocument('doc-2', 'widget for the office'),
            $this->createTestRelevanceDocument('doc-3', 'widget for the garden'),
        ]);

        $result = $this->createCalculator()->calculateWeightingResult(
            new MatchQuery('full-text', 'gadget'),
            $this->createConfigurationTransfer(),
        );

        $this->assertSame(1, $result->getProbeCandidateCount());
        $this->assertSame(0.0, $result->getNormalizedEntropy());
        $this->assertGreaterThan(0.0, $result->getShift(), 'A single dominant candidate shifts weight UP, so the shift itself must be positive.');
    }

    /**
     * Zero hits must produce a result whose fields all read as "nothing to measure", not merely a weight
     * that happens to match the configured baseline.
     *
     * @return void
     */
    public function testWeightingResultForNoHitsCarriesZeroedDiagnostics(): void
    {
        $this->indexTestRelevanceDocuments([
            $this->createTestRelevanceDocument('doc-1', 'gadget for the home'),
        ]);

        $result = $this->createCalculator()->calculateWeightingResult(
            new MatchQuery('full-text', 'no-such-term-anywhere'),
            $this->createConfigurationTransfer(),
        );

        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $result->getConfiguredRelevanceWeight());
        $this->assertSame(static::CONFIGURED_RELEVANCE_WEIGHT, $result->getRelevanceWeight());
        $this->assertSame(0.0, $result->getNormalizedEntropy());
        $this->assertSame(0.0, $result->getShift());
        $this->assertSame(0, $result->getProbeCandidateCount());
    }

    /**
     * @return \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer
     */
    protected function createConfigurationTransfer(): SearchRankingConfigurationStorageTransfer
    {
        return (new SearchRankingConfigurationStorageTransfer())
            ->setRelevanceWeight(static::CONFIGURED_RELEVANCE_WEIGHT)
            ->setEntropyProbeResultSize(10)
            ->setEntropyWeightExponent(1.0)
            ->setEntropyWeightShiftMagnitude(static::SHIFT_MAGNITUDE);
    }

    /**
     * @return \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightCalculator
     */
    protected function createCalculator(): EntropyWeightCalculator
    {
        $searchElasticsearchConfig = new SearchElasticsearchConfig();
        $elasticaClient = (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());

        return new class (
            $elasticaClient,
            $searchElasticsearchConfig,
            new ShannonEntropyCalculator(),
            // Never actually called — resolveIndexName() is overridden below to bypass store resolution
            // entirely, the same way it bypasses the real page index. A real StoreClientInterface isn't
            // needed just to satisfy this constructor parameter.
            $this->createMock(SearchRankingToStoreClientInterface::class),
        ) extends EntropyWeightCalculator {
            /**
             * Hardcoded rather than referencing `TestRelevanceIndexTrait::TEST_RELEVANCE_INDEX_NAME` —
             * an anonymous class can't reach an enclosing test case's trait constant. Must match that
             * constant's value.
             *
             * @return string
             */
            protected function resolveIndexName(): string
            {
                return 'search_ranking_test_relevance';
            }
        };
    }
}
