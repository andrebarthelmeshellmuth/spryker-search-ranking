<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Query;

use Codeception\Test\Unit;
use Elastica\Query;
use Elastica\Query\MatchAll;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder;
use SprykerCommunityTest\Client\SearchRanking\Fixture\TestScoresIndexTrait;

/**
 * INTEGRATION TEST — talks to a real Elasticsearch, against a TEST-OWNED index (`TestScoresIndexTrait`),
 * never the host shop's real page index.
 *
 * `FunctionScoreBuilderTest` (same directory) only asserts on the PHP-level shape of the built query —
 * the array structure, the script source string. It never actually sends that script to an engine, so it
 * cannot catch a painless syntax error, a doc-value guard that doesn't behave as intended, or a
 * `boost_mode`/`score_mode` combination that doesn't do what the unit test's assertions assume. This test
 * closes exactly that gap: it builds a real function_score via `FunctionScoreBuilder`, executes it against
 * real documents, and asserts on the real resulting ranking order.
 *
 * `relevanceWeight` is deliberately set to 0.0 throughout: with `match_all` as the wrapped query, every
 * document's raw `_score` is the same constant (1.0), so leaving `relevanceWeight` at anything above 0
 * would make the text-relevance term identical for every document and the assertions would only be
 * proving `boost_mode: replace` works, not that the business-signal blend does. Zeroing it isolates the
 * one thing this test exists to prove: `(1 - relevanceWeight) * (w_i * doc_value)` actually executes
 * correctly against real per-document `scores.*` values, including the doc-value-missing case.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Query
 * @group FunctionScoreExecutionTest
 */
class FunctionScoreExecutionTest extends Unit
{
    use TestScoresIndexTrait;

    protected function _before(): void
    {
        $this->createTestScoresIndex();
    }

    protected function _after(): void
    {
        $this->deleteTestScoresIndex();
    }

    public function testRealFunctionScoreRanksDocumentsByTheirBusinessSignalValue(): void
    {
        // Arrange
        $this->indexTestDocuments([
            $this->createTestDocument('high', ['scores' => ['top_seller' => 0.9]]),
            $this->createTestDocument('low', ['scores' => ['top_seller' => 0.1]]),
            $this->createTestDocument('none', ['sku' => 'no-scores-field-at-all']),
        ]);

        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 1.0])
            ->setRelevanceWeight(0.0)
            ->setRelevanceSaturationPoint(12.0);

        $functionScore = (new FunctionScoreBuilder())->build(new MatchAll(), $configurationTransfer);
        $this->assertNotNull($functionScore, 'FunctionScoreBuilder should not step aside with a real, non-zero-weighted metric.');

        // Act
        $resultSet = $this->getTestScoresIndex()->search(Query::create($functionScore));

        // Assert
        $resultsById = [];

        foreach ($resultSet->getResults() as $result) {
            $resultsById[$result->getId()] = $result->getScore();
        }

        $this->assertEqualsWithDelta(0.9, $resultsById['high'], 0.0001, 'The doc-value guard should read the real scores.top_seller value.');
        $this->assertEqualsWithDelta(0.1, $resultsById['low'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $resultsById['none'], 0.0001, 'A document with no scores field at all should fall back to 0, not error out.');

        $orderedIds = array_keys($resultsById);
        $this->assertSame(['high', 'low', 'none'], $orderedIds, 'Real engine ranking order should follow the business-signal value, highest first.');
    }
}
