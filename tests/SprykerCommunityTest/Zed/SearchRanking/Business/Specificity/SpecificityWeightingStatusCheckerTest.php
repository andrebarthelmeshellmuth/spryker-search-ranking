<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Specificity;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Business\Specificity\SpecificityWeightingStatusChecker;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Specificity
 * @group SpecificityWeightingStatusCheckerTest
 * Add your own group annotations below this line
 */
class SpecificityWeightingStatusCheckerTest extends Unit
{
    public function testDelegatesStraightToTheSearchRankingClientAndReturnsItsResultUnchangedWhenTrue(): void
    {
        // Arrange
        $searchRankingClientMock = $this->createMock(SearchRankingToSearchRankingClientInterface::class);
        $searchRankingClientMock->expects($this->once())
            ->method('isSpecificityWeightingEnabled')
            ->willReturn(true);

        $checker = new SpecificityWeightingStatusChecker($searchRankingClientMock);

        // Act
        $result = $checker->isEnabled();

        // Assert
        $this->assertTrue($result);
    }

    public function testDelegatesStraightToTheSearchRankingClientAndReturnsItsResultUnchangedWhenFalse(): void
    {
        // Arrange -- proves this doesn't just hardcode true; the Facade this backs used to do exactly
        // that via a second, project-overridable flag that had to be kept in sync with the Client-layer
        // one by hand.
        $searchRankingClientMock = $this->createMock(SearchRankingToSearchRankingClientInterface::class);
        $searchRankingClientMock->expects($this->once())
            ->method('isSpecificityWeightingEnabled')
            ->willReturn(false);

        $checker = new SpecificityWeightingStatusChecker($searchRankingClientMock);

        // Act
        $result = $checker->isEnabled();

        // Assert
        $this->assertFalse($result);
    }
}
