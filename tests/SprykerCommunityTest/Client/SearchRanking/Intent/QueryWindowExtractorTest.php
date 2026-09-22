<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Intent;

use Codeception\Test\Unit;
use SprykerCommunity\Shared\SearchRanking\Intent\QueryWindowExtractor;

/**
 * Tests the Shared-layer {@see QueryWindowExtractor} from this package's Client suite — no
 * standalone Shared suite exists yet for this package.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Intent
 * @group QueryWindowExtractorTest
 * @group Portable
 */
class QueryWindowExtractorTest extends Unit
{
    public function testSingleWordQueryReturnsExactlyOneWindow(): void
    {
        $this->assertSame(['Topstar'], QueryWindowExtractor::extractWindows('Topstar'));
    }

    public function testThreeWordQueryReturnsEveryOneTwoAndThreeWordWindowLongestFirst(): void
    {
        // Arrange
        $windows = QueryWindowExtractor::extractWindows('Topstar swivel chair');

        // Assert — 3-word window first, then every 2-word window, then every 1-word window.
        $this->assertSame([
            'Topstar swivel chair',
            'Topstar swivel',
            'swivel chair',
            'Topstar',
            'swivel',
            'chair',
        ], $windows);
    }

    public function testBlankQueryReturnsNoWindows(): void
    {
        $this->assertSame([], QueryWindowExtractor::extractWindows('   '));
    }

    public function testCollapsesRepeatedWhitespaceBetweenTokensToASingleSpaceInEachWindow(): void
    {
        $this->assertSame(['a b'], array_slice(QueryWindowExtractor::extractWindows("a  \t b"), 0, 1));
    }
}
