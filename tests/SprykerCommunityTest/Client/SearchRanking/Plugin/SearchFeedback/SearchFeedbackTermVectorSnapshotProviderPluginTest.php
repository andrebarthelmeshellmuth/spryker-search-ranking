<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Plugin\SearchFeedback;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;
use SprykerCommunity\Client\SearchRanking\Plugin\SearchFeedback\SearchFeedbackTermVectorSnapshotProviderPlugin;
use SprykerCommunity\Client\SearchRanking\SearchRankingClient;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Plugin
 * @group SearchFeedback
 * @group SearchFeedbackTermVectorSnapshotProviderPluginTest
 * @group Portable
 */
class SearchFeedbackTermVectorSnapshotProviderPluginTest extends Unit
{
    public function testReturnsNullWhenSpecificityWeightingDidNotRunThisRequest(): void
    {
        // Arrange
        $clientMock = $this->createMock(SearchRankingClient::class);
        $clientMock->method('getLastSpecificityWeightingResult')->willReturn(null);

        $plugin = new SearchFeedbackTermVectorSnapshotProviderPlugin();
        $plugin->setClient($clientMock);

        // Act & Assert
        $this->assertNull($plugin->getTermVectorSnapshot());
    }

    public function testReturnsTheJsonEncodedSpecificityWeightingResultWhenOneExists(): void
    {
        // Arrange
        $resultTransfer = (new SearchRankingSpecificityWeightingResultTransfer())
            ->setConfiguredRelevanceWeight(1.0)
            ->setRelevanceWeight(1.2)
            ->setNormalizedSpecificity(0.5)
            ->setShift(0.2)
            ->setQueryTermCount(3)
            ->setSpecificityWeightExponent(2.0)
            ->setSpecificityWeightShiftMagnitude(0.4);

        $clientMock = $this->createMock(SearchRankingClient::class);
        $clientMock->method('getLastSpecificityWeightingResult')->willReturn($resultTransfer);

        $plugin = new SearchFeedbackTermVectorSnapshotProviderPlugin();
        $plugin->setClient($clientMock);

        // Act
        $snapshot = $plugin->getTermVectorSnapshot();

        // Assert
        $this->assertNotNull($snapshot);
        $decoded = json_decode($snapshot, true);
        $this->assertSame(1.2, $decoded['relevanceWeight']);
        $this->assertSame(0.5, $decoded['normalizedSpecificity']);
    }
}
