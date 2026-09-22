<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Intent;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchRanking\Intent\EntityTermNormalizer;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Intent
 * @group EntityTermNormalizerTest
 * @group Portable
 */
class EntityTermNormalizerTest extends Unit
{
    public function testLowercasesTheTerm(): void
    {
        $this->assertSame('topstar', EntityTermNormalizer::normalize('TopStar'));
    }

    public function testTrimsLeadingAndTrailingWhitespace(): void
    {
        $this->assertSame('gadgets', EntityTermNormalizer::normalize('  gadgets  '));
    }

    public function testCollapsesInternalRunsOfWhitespaceToASingleSpace(): void
    {
        $this->assertSame('gas boiler', EntityTermNormalizer::normalize("gas\t\n  boiler"));
    }

    public function testCombinesCasingSpacingAndTrimmingIntoTheSameNormalForm(): void
    {
        $this->assertSame('gas boiler', EntityTermNormalizer::normalize('  Gas    BOILER  '));
    }

    public function testAWhitespaceOnlyTermNormalizesToAnEmptyString(): void
    {
        $this->assertSame('', EntityTermNormalizer::normalize("   \t  "));
    }

    public function testAnAlreadyNormalizedTermIsUnchanged(): void
    {
        $this->assertSame('m23484', EntityTermNormalizer::normalize('m23484'));
    }

    public function testAnEmptyStringStaysEmpty(): void
    {
        $this->assertSame('', EntityTermNormalizer::normalize(''));
    }
}
