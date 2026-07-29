<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult;
use SprykerCommunity\Client\SearchRanking\SearchRankingClient;

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
 */
class SearchRankingClientTest extends Unit
{
    /**
     * @return void
     */
    public function testHasNoRememberedEntropyWeightingResultInitially(): void
    {
        // Act & Assert
        $this->assertNull((new SearchRankingClient())->getLastEntropyWeightingResult());
    }

    /**
     * The whole point of this holder: it must stay the same across calls so two independent plugins in
     * the same request can exchange this value without knowing about each other — see
     * SearchRankingClientInterface::rememberLastEntropyWeightingResult()'s docblock.
     *
     * @return void
     */
    public function testReturnsTheLastRememberedEntropyWeightingResult(): void
    {
        // Arrange
        $client = new SearchRankingClient();
        $entropyWeightingResult = new EntropyWeightingResult(0.75, 0.9, 0.1, 0.15, 10);

        // Act
        $client->rememberLastEntropyWeightingResult($entropyWeightingResult);

        // Assert
        $this->assertSame($entropyWeightingResult, $client->getLastEntropyWeightingResult());
    }

    /**
     * A later query in the same request (e.g. after a category/browse page where entropy weighting
     * doesn't apply) must overwrite an earlier result rather than leave it in place.
     *
     * @return void
     */
    public function testOverwritesAPreviouslyRememberedResultWithNull(): void
    {
        // Arrange
        $client = new SearchRankingClient();
        $client->rememberLastEntropyWeightingResult(new EntropyWeightingResult(0.75, 0.9, 0.1, 0.15, 10));

        // Act
        $client->rememberLastEntropyWeightingResult(null);

        // Assert
        $this->assertNull($client->getLastEntropyWeightingResult());
    }
}
