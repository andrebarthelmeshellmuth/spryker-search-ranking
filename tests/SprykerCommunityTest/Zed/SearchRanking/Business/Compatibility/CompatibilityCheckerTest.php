<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Compatibility;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingEngineCapabilityTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Compatibility\CompatibilityChecker;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Compatibility
 * @group CompatibilityCheckerTest
 * Add your own group annotations below this line
 * @group Portable
 */
class CompatibilityCheckerTest extends Unit
{
    public function testDelegatesStraightToTheSearchRankingClientAndReturnsItsResultUnchanged(): void
    {
        // Arrange
        $capabilityTransfer = (new SearchRankingEngineCapabilityTransfer())
            ->setName('function_score + script_score (painless)')
            ->setIsSupported(true)
            ->setDetail('Query type is recognized and parses successfully.');

        $compatibilityTransfer = (new SearchRankingEngineCompatibilityTransfer())
            ->setDistribution('opensearch')
            ->setVersion('1.3.4')
            ->addCapability($capabilityTransfer);

        $searchRankingClientMock = $this->createMock(SearchRankingToSearchRankingClientInterface::class);
        $searchRankingClientMock->expects($this->once())
            ->method('checkEngineCompatibility')
            ->willReturn($compatibilityTransfer);

        $checker = new CompatibilityChecker($searchRankingClientMock);

        // Act
        $result = $checker->checkCompatibility();

        // Assert
        $this->assertSame($compatibilityTransfer, $result);
    }
}
