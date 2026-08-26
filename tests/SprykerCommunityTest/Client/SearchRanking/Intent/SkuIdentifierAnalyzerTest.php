<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Intent;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface;
use SprykerCommunity\Client\SearchRanking\Intent\SkuIdentifierAnalyzer;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Intent
 * @group SkuIdentifierAnalyzerTest
 * @group Portable
 */
class SkuIdentifierAnalyzerTest extends Unit
{
    public function testExactDictionaryHitSetsIdentifierMatchAndTheRealMatchedValue(): void
    {
        // Arrange
        $analyzer = $this->createAnalyzer(dictionaryHit: true);
        $queryContextTransfer = $this->createQueryContext('M23484');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertTrue($result->getIsIdentifierMatch());
        $this->assertSame('M23484', $result->getMatchedIdentifierValue());
    }

    public function testDictionaryMissLeavesQueryContextUnchanged(): void
    {
        // Arrange
        $analyzer = $this->createAnalyzer(dictionaryHit: false);
        $queryContextTransfer = $this->createQueryContext('gas boiler');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertFalse($result->getIsIdentifierMatch());
        $this->assertNull($result->getMatchedIdentifierValue());
    }

    public function testBlankSearchStringIsLeftUnchanged(): void
    {
        // Arrange
        $analyzer = $this->createAnalyzer(dictionaryHit: false);
        $queryContextTransfer = $this->createQueryContext('   ');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertFalse($result->getIsIdentifierMatch());
    }

    protected function createQueryContext(string $searchString): SearchRankingQueryContextTransfer
    {
        return (new SearchRankingQueryContextTransfer())
            ->setSearchString($searchString)
            ->setStoreName('DE')
            ->setLocaleName('de_DE')
            ->setIsIdentifierMatch(false);
    }

    protected function createAnalyzer(bool $dictionaryHit): SkuIdentifierAnalyzer
    {
        $entityLookup = new class ($dictionaryHit) implements EntityLookupInterface {
            public function __construct(protected bool $dictionaryHit)
            {
            }

            public function exists(string $term): bool
            {
                return $this->dictionaryHit;
            }

            /**
             * @return array<int, string>
             */
            public function suggest(string $prefix, int $limit): array
            {
                return [];
            }
        };

        return new SkuIdentifierAnalyzer($entityLookup);
    }
}
