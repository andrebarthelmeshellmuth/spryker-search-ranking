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
 */
class FunctionScoreBuilderTest extends Unit
{
    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
     *
     * @return void
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
}
