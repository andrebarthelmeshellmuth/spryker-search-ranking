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
use SprykerCommunity\Client\SearchRanking\Plugin\SearchFeedback\SearchFeedbackTermVectorSnapshotRestorerPlugin;
use SprykerCommunity\Client\SearchRanking\SearchRankingClient;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Plugin
 * @group SearchFeedback
 * @group SearchFeedbackTermVectorSnapshotRestorerPluginTest
 * @group Portable
 */
class SearchFeedbackTermVectorSnapshotRestorerPluginTest extends Unit
{
    /**
     * Round-trips through the REAL provider plugin's own encoding (rather than hand-writing JSON) so this
     * test breaks if the two ever drift out of sync with each other — the exact failure mode that caused
     * the live bug this plugin exists to fix in the first place (the overlay silently reading LIVE data
     * because nothing ever restored the frozen value, not a decode mismatch — but a round-trip test is
     * still the strongest guard against a FUTURE mismatch).
     */
    public function testRememberLastSpecificityWeightingResultIsCalledWithTheDecodedTransfer(): void
    {
        // Arrange
        $originalResultTransfer = (new SearchRankingSpecificityWeightingResultTransfer())
            ->setConfiguredRelevanceWeight(0.5)
            ->setRelevanceWeight(0.01)
            ->setNormalizedSpecificity(0.463)
            ->setShift(-0.018)
            ->setQueryTermCount(3)
            ->setSpecificityWeightExponent(2.0)
            ->setSpecificityWeightShiftMagnitude(0.4)
            ->setRawSpecificity(1.7);

        $providerClientMock = $this->createMock(SearchRankingClient::class);
        $providerClientMock->method('getLastSpecificityWeightingResult')->willReturn($originalResultTransfer);
        $providerPlugin = new SearchFeedbackTermVectorSnapshotProviderPlugin();
        $providerPlugin->setClient($providerClientMock);
        $termVectorSnapshot = $providerPlugin->getTermVectorSnapshot();
        $this->assertNotNull($termVectorSnapshot);

        $restorerClientMock = $this->createMock(SearchRankingClient::class);
        $restorerClientMock->expects($this->once())
            ->method('rememberLastSpecificityWeightingResult')
            ->with($this->callback(fn (SearchRankingSpecificityWeightingResultTransfer $restoredTransfer): bool => $restoredTransfer->getRelevanceWeight() === 0.01
                && $restoredTransfer->getConfiguredRelevanceWeight() === 0.5
                && $restoredTransfer->getNormalizedSpecificity() === 0.463));

        $restorerPlugin = new SearchFeedbackTermVectorSnapshotRestorerPlugin();
        $restorerPlugin->setClient($restorerClientMock);

        // Act
        $restorerPlugin->restoreTermVectorSnapshot($termVectorSnapshot);
    }

    public function testMalformedJsonIsSilentlyIgnoredRatherThanThrowing(): void
    {
        // Arrange
        $clientMock = $this->createMock(SearchRankingClient::class);
        $clientMock->expects($this->never())->method('rememberLastSpecificityWeightingResult');

        $restorerPlugin = new SearchFeedbackTermVectorSnapshotRestorerPlugin();
        $restorerPlugin->setClient($clientMock);

        // Act & Assert — no exception is part of the assertion.
        $restorerPlugin->restoreTermVectorSnapshot('not valid json{{{');
    }

    public function testJsonThatDoesNotDecodeToAnArrayIsSilentlyIgnored(): void
    {
        // Arrange
        $clientMock = $this->createMock(SearchRankingClient::class);
        $clientMock->expects($this->never())->method('rememberLastSpecificityWeightingResult');

        $restorerPlugin = new SearchFeedbackTermVectorSnapshotRestorerPlugin();
        $restorerPlugin->setClient($clientMock);

        // Act & Assert — a bare JSON string/number/bool is valid JSON but not usable here.
        $restorerPlugin->restoreTermVectorSnapshot('"just a string"');
    }
}
