<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Intent;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Client\SearchRanking\Intent\BrandAnalyzer;
use SprykerCommunity\Client\SearchRanking\Intent\EntityLookupInterface;

/**
 * Hand-picked real query strings against a fixture brand set (`Schomburg`, `Topstar` — real brand values
 * this demoshop's own catalog imports, see `data/import/common/common/product_abstract.csv`).
 *
 * The disambiguation tests use "office" as the fixture — NOT a synthetic collision. This demoshop's own
 * `data/import/common/common/product_abstract.csv` genuinely carries `brand,office` on real product rows,
 * and this catalog's entity-lookup ALSO genuinely indexes "office" as a category (verified live against
 * the `spryker_b2b_marketplace_dev_de_entity-lookup` OpenSearch index before this fix was built) — a real
 * instance of the "apple: brand or fruit?" ambiguity, not a made-up example.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Intent
 * @group BrandAnalyzerTest
 * @group Portable
 */
class BrandAnalyzerTest extends Unit
{
    public function testDetectsABrandNameAppearingAsAWordWindowWithinALongerQuery(): void
    {
        // Arrange
        $analyzer = new BrandAnalyzer(
            $this->createFixtureLookup(['topstar', 'schomburg']),
            $this->createFixtureLookup([]),
        );
        $queryContextTransfer = $this->createQueryContext('Topstar swivel chair');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertSame('Topstar', $result->getDetectedBrand());
    }

    public function testWholeStringBrandMatchIsDetectedToo(): void
    {
        // Arrange
        $analyzer = new BrandAnalyzer(
            $this->createFixtureLookup(['schomburg']),
            $this->createFixtureLookup([]),
        );
        $queryContextTransfer = $this->createQueryContext('Schomburg');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertSame('Schomburg', $result->getDetectedBrand());
    }

    public function testNoKnownBrandLeavesDetectedBrandNull(): void
    {
        // Arrange
        $analyzer = new BrandAnalyzer(
            $this->createFixtureLookup(['topstar']),
            $this->createFixtureLookup([]),
        );
        $queryContextTransfer = $this->createQueryContext('standing desk 1200mm');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertNull($result->getDetectedBrand());
    }

    public function testBlankSearchStringIsLeftUnchanged(): void
    {
        // Arrange
        $analyzer = new BrandAnalyzer(
            $this->createFixtureLookup(['topstar']),
            $this->createFixtureLookup([]),
        );
        $queryContextTransfer = $this->createQueryContext('   ');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertNull($result->getDetectedBrand());
    }

    /**
     * The real "office" collision: "office" is present as a placeholder brand value in this demoshop's own
     * seed data, but ALSO exists as a genuine category. A bare "office chair" query is a generic/category
     * query, not a navigational brand query — the fix must NOT set `detectedBrand` for it.
     */
    public function testAmbiguousBrandAndCategoryTermDoesNotSetDetectedBrand(): void
    {
        // Arrange
        $analyzer = new BrandAnalyzer(
            $this->createFixtureLookup(['office']),
            $this->createFixtureLookup(['office']),
        );
        $queryContextTransfer = $this->createQueryContext('office chair');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertNull($result->getDetectedBrand());
    }

    /**
     * Same real collision, the other judgment-set-shaped query — "paper for the office printer" — must
     * also not trigger a false-positive brand detection on "office".
     */
    public function testAmbiguousTermInALongerQueryDoesNotSetDetectedBrand(): void
    {
        // Arrange
        $analyzer = new BrandAnalyzer(
            $this->createFixtureLookup(['office']),
            $this->createFixtureLookup(['office']),
        );
        $queryContextTransfer = $this->createQueryContext('paper for the office printer');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertNull($result->getDetectedBrand());
    }

    /**
     * A genuine, unambiguous brand match (present in the brand lookup only, e.g. real judgment-set brand
     * names like "Topstar") must still fire at full strength, even in a factory-shaped BrandAnalyzer that
     * ALSO holds a non-empty category lookup with unrelated entries — disambiguation must not degrade
     * unambiguous matches.
     */
    public function testUnambiguousBrandMatchStillFiresWhenCategoryLookupHasUnrelatedEntries(): void
    {
        // Arrange
        $analyzer = new BrandAnalyzer(
            $this->createFixtureLookup(['topstar', 'office']),
            $this->createFixtureLookup(['office', 'chairs']),
        );
        $queryContextTransfer = $this->createQueryContext('Topstar swivel chair');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertSame('Topstar', $result->getDetectedBrand());
    }

    /**
     * When the longest ambiguous window is suppressed, a shorter/other window that is a genuine
     * unambiguous brand match must still be found — suppression skips just the ambiguous window, it does
     * not abort the whole scan.
     */
    public function testUnambiguousWindowIsStillFoundAfterAnAmbiguousWindowIsSkipped(): void
    {
        // Arrange
        $analyzer = new BrandAnalyzer(
            $this->createFixtureLookup(['office', 'topstar']),
            $this->createFixtureLookup(['office']),
        );
        $queryContextTransfer = $this->createQueryContext('office Topstar chair');

        // Act
        $result = $analyzer->analyze($queryContextTransfer);

        // Assert
        $this->assertSame('Topstar', $result->getDetectedBrand());
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
