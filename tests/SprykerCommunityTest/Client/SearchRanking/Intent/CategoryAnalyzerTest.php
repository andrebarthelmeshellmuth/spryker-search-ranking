<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Intent;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Client\SearchRanking\Intent\CategoryAnalyzer;
use SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface;

/**
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Intent
 * @group CategoryAnalyzerTest
 * @group Portable
 */
class CategoryAnalyzerTest extends Unit
{
    public function testDetectsAMultiWordCategoryNameAppearingWithinALongerQuery(): void
    {
        // Arrange
        $analyzer = new CategoryAnalyzer($this->createFixtureLookup(['office chairs', 'standing desks']));
        $queryContextTransfer = $this->createQueryContext('black office chairs with armrests');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertSame('office chairs', $result->getDetectedCategory());
    }

    public function testLongerWindowWinsOverAShorterFalsePositiveSubstring(): void
    {
        // Arrange — "standing desks" (2-word real category) should win over "desks" alone if that were
        // also (hypothetically) a fixture term, since windows are checked longest-first.
        $analyzer = new CategoryAnalyzer($this->createFixtureLookup(['standing desks', 'desks']));
        $queryContextTransfer = $this->createQueryContext('standing desks 1200mm');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertSame('standing desks', $result->getDetectedCategory());
    }

    public function testNoKnownCategoryLeavesDetectedCategoryNull(): void
    {
        // Arrange
        $analyzer = new CategoryAnalyzer($this->createFixtureLookup(['office chairs']));
        $queryContextTransfer = $this->createQueryContext('M23484');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertNull($result->getDetectedCategory());
    }

    protected function createQueryContext(string $searchString): SearchRankingQueryContextTransfer
    {
        return (new SearchRankingQueryContextTransfer())
            ->setSearchString($searchString)
            ->setStoreName('DE')
            ->setLocaleName('de_DE')
            ->setIsIdentifierMatch(false);
    }

    /**
     * @param array<int, string> $normalizedFixtureTerms
     */
    protected function createFixtureLookup(array $normalizedFixtureTerms): EntityLookupInterface
    {
        return new class ($normalizedFixtureTerms) implements EntityLookupInterface {
            /**
             * @param array<int, string> $normalizedFixtureTerms
             */
            public function __construct(protected array $normalizedFixtureTerms)
            {
            }

            public function exists(string $term): bool
            {
                return in_array(mb_strtolower(trim($term)), $this->normalizedFixtureTerms, true);
            }

            /**
             * @return array<int, string>
             */
            public function suggest(string $prefix, int $limit): array
            {
                unset($prefix, $limit);

                return [];
            }
        };
    }
}
