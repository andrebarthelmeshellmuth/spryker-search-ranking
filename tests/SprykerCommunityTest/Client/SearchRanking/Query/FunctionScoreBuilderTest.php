<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Query;

use Codeception\Test\Unit;
use Elastica\Query\BoolQuery;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Query
 * @group FunctionScoreBuilderTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRanking\SearchRankingClientTester $tester
 * @group NeedsSearch
 */
class FunctionScoreBuilderTest extends Unit
{
    public function testBuildsFunctionScoreWithWeightParamsAndGuardedDocValues(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5, 'pdp_impressions' => 0.3])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $functionScore = (new FunctionScoreBuilder())->build(new BoolQuery(), $configurationTransfer);

        // Assert
        $this->assertNotNull($functionScore);
        $functionScoreArray = $functionScore->toArray()['function_score'];
        $this->assertSame('replace', $functionScoreArray['boost_mode']);
        $this->assertSame('first', $functionScoreArray['score_mode']);

        $script = $functionScoreArray['functions'][0]['script_score']['script'];
        $this->assertSame(['w0' => 0.5, 'w1' => 0.3, 'relevanceWeight' => 0.6, 'relevanceSaturationPoint' => 12.0], $script['params']);
        $this->assertStringContainsString('params.relevanceWeight * (_score / (_score + params.relevanceSaturationPoint))', $script['source']);
        $this->assertStringContainsString("params.w0 * ((doc.containsKey('scores.top_seller') && doc['scores.top_seller'].size() > 0) ? doc['scores.top_seller'].value : 0)", $script['source']);
        $this->assertStringContainsString("params.w1 * ((doc.containsKey('scores.pdp_impressions')", $script['source']);
        $this->assertStringContainsString('(1 - params.relevanceWeight) * (', $script['source']);
    }

    public function testSkipsZeroWeightedMetrics(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5, 'muted_metric' => 0.0])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $functionScore = (new FunctionScoreBuilder())->build(new BoolQuery(), $configurationTransfer);

        // Assert
        $script = $functionScore->toArray()['function_score']['functions'][0]['script_score']['script'];
        $this->assertStringNotContainsString('muted_metric', $script['source']);
        $this->assertSame(['w0' => 0.5, 'relevanceWeight' => 0.6, 'relevanceSaturationPoint' => 12.0], $script['params']);
    }

    /**
     * Metric names are embedded into the painless source, so anything not matching the
     * strict name pattern (only enforceable via the Zed form, not via data import) must
     * never reach the script.
     */
    public function testRejectsMetricNamesThatCouldInjectScriptCode(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(["evil'] + params.relevanceWeight; //" => 1.0, 'valid_metric' => 0.4])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $functionScore = (new FunctionScoreBuilder())->build(new BoolQuery(), $configurationTransfer);

        // Assert
        $script = $functionScore->toArray()['function_score']['functions'][0]['script_score']['script'];
        $this->assertStringNotContainsString('evil', $script['source']);
        $this->assertStringContainsString('valid_metric', $script['source']);
    }

    public function testReturnsNullWhenNoUsableSignalTermsRemain(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['muted' => 0.0])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $functionScore = (new FunctionScoreBuilder())->build(new BoolQuery(), $configurationTransfer);

        // Assert
        $this->assertNull($functionScore);
    }

    public function testWrapsTheGivenQuery(): void
    {
        // Arrange
        $wrappedQuery = new BoolQuery();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0);

        // Act
        $functionScore = (new FunctionScoreBuilder())->build($wrappedQuery, $configurationTransfer);

        // Assert
        $this->assertArrayHasKey('bool', $functionScore->toArray()['function_score']['query']);
    }

    /**
     * Regression guard: `alpha = 1.0` (the documented default) with no query vector must produce a
     * script byte-identical to the pre-hybrid-search formula — no added complexity, no `alpha`/
     * `queryVector` params at all.
     */
    public function testProducesByteIdenticalScriptWhenAlphaIsDefaultAndNoQueryVectorGiven(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0)
            ->setAlpha(1.0);

        // Act
        $functionScoreWithoutAlphaConcept = (new FunctionScoreBuilder())->build(
            new BoolQuery(),
            (new SearchRankingConfigurationStorageTransfer())
                ->setMetricWeights(['top_seller' => 0.5])
                ->setRelevanceWeight(0.6)
                ->setRelevanceSaturationPoint(12.0),
        );
        $functionScoreWithDefaultAlpha = (new FunctionScoreBuilder())->build(new BoolQuery(), $configurationTransfer);
        $functionScoreWithVectorButDefaultAlpha = (new FunctionScoreBuilder())->build(
            new BoolQuery(),
            $configurationTransfer,
            [0.1, 0.2, 0.3],
        );

        // Assert
        $scriptWithoutAlphaConcept = $functionScoreWithoutAlphaConcept->toArray()['function_score']['functions'][0]['script_score']['script'];
        $scriptWithDefaultAlpha = $functionScoreWithDefaultAlpha->toArray()['function_score']['functions'][0]['script_score']['script'];
        $scriptWithVectorButDefaultAlpha = $functionScoreWithVectorButDefaultAlpha->toArray()['function_score']['functions'][0]['script_score']['script'];

        $this->assertSame($scriptWithoutAlphaConcept, $scriptWithDefaultAlpha);
        $this->assertSame($scriptWithoutAlphaConcept, $scriptWithVectorButDefaultAlpha);
        $this->assertArrayNotHasKey('alpha', $scriptWithVectorButDefaultAlpha['params']);
        $this->assertArrayNotHasKey('queryVector', $scriptWithVectorButDefaultAlpha['params']);
        $this->assertStringNotContainsString('cosineSimilarity', $scriptWithVectorButDefaultAlpha['source']);
    }

    public function testBlendsInSemanticTermWhenQueryVectorGivenAndAlphaBelowOne(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['top_seller' => 0.5])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0)
            ->setAlpha(0.4);

        // Act
        $functionScore = (new FunctionScoreBuilder())->build(new BoolQuery(), $configurationTransfer, [0.1, -0.2, 0.3]);

        // Assert
        $script = $functionScore->toArray()['function_score']['functions'][0]['script_score']['script'];
        $this->assertSame(0.4, $script['params']['alpha']);
        $this->assertSame([0.1, -0.2, 0.3], $script['params']['queryVector']);
        $this->assertStringContainsString("cosineSimilarity(params.queryVector, doc['embedding'])", $script['source']);
        $this->assertStringContainsString("doc.containsKey('embedding') && doc['embedding'].size() > 0", $script['source']);
        // Per-document fallback: a product without a stored embedding still gets pure saturated _score.
        $this->assertStringContainsString('_score / (_score + params.relevanceSaturationPoint)) : (', $script['source']);
    }

    public function testIgnoresQueryVectorWhenNoUsableSignalTermsRemainEvenBelowDefaultAlpha(): void
    {
        // Arrange — no active metrics: build() already returns null before the semantic term matters.
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights(['muted' => 0.0])
            ->setRelevanceWeight(0.6)
            ->setRelevanceSaturationPoint(12.0)
            ->setAlpha(0.4);

        // Act
        $functionScore = (new FunctionScoreBuilder())->build(new BoolQuery(), $configurationTransfer, [0.1, 0.2]);

        // Assert
        $this->assertNull($functionScore);
    }
}
