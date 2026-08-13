<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;
use SprykerCommunity\Client\SearchRanking\SearchRankingClient;
use SprykerCommunity\Client\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Client\SearchRanking\SearchRankingFactory;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group SearchRankingClientTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRanking\SearchRankingClientTester $tester
 * @group Portable
 */
class SearchRankingClientTest extends Unit
{
    public function testHasNoRememberedSpecificityWeightingResultInitially(): void
    {
        // Act & Assert
        $this->assertNull((new SearchRankingClient())->getLastSpecificityWeightingResult());
    }

    /**
     * The whole point of this holder: it must stay the same across calls so two independent plugins in
     * the same request can exchange this value without knowing about each other — see
     * SearchRankingClientInterface::rememberLastSpecificityWeightingResult()'s docblock.
     */
    public function testReturnsTheLastRememberedSpecificityWeightingResult(): void
    {
        // Arrange
        $client = new SearchRankingClient();
        $specificityWeightingResult = (new SearchRankingSpecificityWeightingResultTransfer())->setConfiguredRelevanceWeight(0.75)->setRelevanceWeight(0.9)->setNormalizedSpecificity(0.1)->setShift(0.15)->setQueryTermCount(10);

        // Act
        $client->rememberLastSpecificityWeightingResult($specificityWeightingResult);

        // Assert
        $this->assertSame($specificityWeightingResult, $client->getLastSpecificityWeightingResult());
    }

    /**
     * A later query in the same request (e.g. after a category/browse page where specificity weighting
     * doesn't apply) must overwrite an earlier result rather than leave it in place.
     */
    public function testOverwritesAPreviouslyRememberedResultWithNull(): void
    {
        // Arrange
        $client = new SearchRankingClient();
        $client->rememberLastSpecificityWeightingResult((new SearchRankingSpecificityWeightingResultTransfer())->setConfiguredRelevanceWeight(0.75)->setRelevanceWeight(0.9)->setNormalizedSpecificity(0.1)->setShift(0.15)->setQueryTermCount(10));

        // Act
        $client->rememberLastSpecificityWeightingResult(null);

        // Assert
        $this->assertNull($client->getLastSpecificityWeightingResult());
    }

    /**
     * The whole point of this method existing: it must resolve through THIS Client's own, Locator-resolved
     * `getFactory()->getConfig()` — the only place a project override of `isSpecificityWeightingEnabled()`
     * actually takes effect — rather than referencing `Shared\SearchRanking\SearchRankingConfig` directly,
     * which has no project-override path at all. See both classes' docblocks for the full history.
     */
    public function testIsSpecificityWeightingEnabledDelegatesToTheLocatorResolvedClientConfig(): void
    {
        // Arrange
        $configMock = $this->createMock(SearchRankingConfig::class);
        $configMock->method('isSpecificityWeightingEnabled')->willReturn(true);

        $factoryMock = $this->createMock(SearchRankingFactory::class);
        $factoryMock->method('getConfig')->willReturn($configMock);

        $client = new SearchRankingClient();
        $client->setFactory($factoryMock);

        // Act & Assert
        $this->assertTrue($client->isSpecificityWeightingEnabled());
    }

    public function testGetSpecificityProbeFieldSearchAnalyzersDelegatesToTheLocatorResolvedClientConfig(): void
    {
        // Arrange
        $fieldToSearchAnalyzer = ['full-text' => 'fulltext_search_analyzer'];

        $configMock = $this->createMock(SearchRankingConfig::class);
        $configMock->method('getSpecificityProbeFieldSearchAnalyzers')->willReturn($fieldToSearchAnalyzer);

        $factoryMock = $this->createMock(SearchRankingFactory::class);
        $factoryMock->method('getConfig')->willReturn($configMock);

        $client = new SearchRankingClient();
        $client->setFactory($factoryMock);

        // Act & Assert
        $this->assertSame($fieldToSearchAnalyzer, $client->getSpecificityProbeFieldSearchAnalyzers());
    }
}
