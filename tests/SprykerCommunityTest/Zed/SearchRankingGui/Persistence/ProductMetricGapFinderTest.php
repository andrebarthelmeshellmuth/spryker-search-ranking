<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGui\Persistence;

use Codeception\Test\Unit;
use Orm\Zed\Product\Persistence\SpyProductAbstract;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfigQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapFinder;

/**
 * INTEGRATION TEST — real database, real rows, never mocked: this is raw SQL (see `ProductMetricGapFinder`'s
 * own docblock for why), so a mocked connection could only ever confirm the PHP shaped a query string, never
 * that the CROSS JOIN + LEFT JOIN + IS NULL actually returns the right rows, that the sort/search whitelist
 * actually protects against arbitrary column-name interpolation, or that parameters actually bind correctly.
 *
 * A brand-new test metric legitimately has EVERY real catalog product as a gap too (zero coverage means
 * zero coverage) — this demoshop's own real catalog is not empty, so assertions here are scoped through a
 * search term unique to this test's own fixture SKUs. That token is deliberately NEVER also present in the
 * test metric's own name: the search condition is `sku LIKE ? OR metric.name LIKE ?` (an intentional OR —
 * a real admin searching "top" should find both a metric named "top_seller" AND any product whose SKU
 * happens to contain "top"), so if a test's metric name shared its product-search token, that metric-name
 * branch alone would match every real product paired with it (a brand-new metric already has zero
 * coverage against the WHOLE catalog) — silently defeating the intended per-product scoping. Metric names
 * and product SKUs therefore always use two DIFFERENT tokens below, on purpose.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingGui
 * @group Persistence
 * @group ProductMetricGapFinderTest
 *
 * @property \SprykerCommunityTest\Zed\SearchRankingGui\SearchRankingGuiZedTester $tester
 * @group NeedsDatabase
 */
class ProductMetricGapFinderTest extends Unit
{
    public function testFindGapsAcrossAllMetricsWhenNoMetricIsSelected(): void
    {
        // Arrange — metric names use "mtr", product SKUs use "prd": two disjoint tokens, see class docblock.
        $productToken = 'prdfindall' . uniqid();
        $metricA = $this->createTestMetric('mtrfindalla' . uniqid());
        $metricB = $this->createTestMetric('mtrfindallb' . uniqid());
        [$fullyCovered, $missingA, $missingB] = [
            $this->createTestProductAbstract($productToken . '-covered'),
            $this->createTestProductAbstract($productToken . '-missing-a'),
            $this->createTestProductAbstract($productToken . '-missing-b'),
        ];

        $this->createTestProductMetric($metricA, $fullyCovered);
        $this->createTestProductMetric($metricB, $fullyCovered);
        $this->createTestProductMetric($metricB, $missingA);
        $this->createTestProductMetric($metricA, $missingB);

        $finder = new ProductMetricGapFinder();

        // Act — scoped via the product token so only this test's own fixtures can match, regardless of
        // whatever else already exists in the real catalog.
        $gaps = $finder->findGaps(null, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, $productToken, 'sku', 'asc', 100, 0);

        // Assert — further scoped to gaps against THIS test's own two metrics specifically: fullyCovered
        // is a brand-new product, so it is (correctly) still a real gap for every OTHER active metric in
        // the system (the real demo metrics among them) — that's expected, not a bug, and irrelevant to
        // what this test is actually asserting (that it has no gap against metricA/metricB specifically).
        $testMetricNames = [$metricA->getName(), $metricB->getName()];
        $gapsBySku = $this->indexAllBySku(array_values(array_filter(
            $gaps,
            static fn (array $gap) => in_array($gap['missing_metric_name'], $testMetricNames, true),
        )));

        $this->assertSame($metricA->getName(), $gapsBySku[$missingA->getSku()]['missing_metric_name']);
        $this->assertSame($metricB->getName(), $gapsBySku[$missingB->getSku()]['missing_metric_name']);
        $this->assertArrayNotHasKey($fullyCovered->getSku(), $gapsBySku);
        $this->assertCount(2, $gapsBySku, 'Only the two genuine gaps against THIS test\'s own metrics should remain, not the fully-covered product.');
    }

    public function testFindGapsRestrictedToOneSelectedMetric(): void
    {
        // Arrange
        $productToken = 'prdfindone' . uniqid();
        $metricA = $this->createTestMetric('mtrfindonea' . uniqid());
        $metricB = $this->createTestMetric('mtrfindoneb' . uniqid());
        [$missingA, $missingB] = [
            $this->createTestProductAbstract($productToken . '-missing-a'),
            $this->createTestProductAbstract($productToken . '-missing-b'),
        ];

        $this->createTestProductMetric($metricB, $missingA);
        $this->createTestProductMetric($metricA, $missingB);

        $finder = new ProductMetricGapFinder();

        // Act
        $gaps = $finder->findGaps($metricA->getIdSearchRankingMetric(), SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, $productToken, 'sku', 'asc', 100, 0);

        // Assert
        $skus = array_column($gaps, 'sku');

        $this->assertContains($missingA->getSku(), $skus, 'missingA has no row for metric A — it is a real gap for the selected metric.');
        $this->assertNotContains($missingB->getSku(), $skus, 'missingB is missing metric B, not metric A — it must not appear when filtered to metric A.');
    }

    public function testSearchTermMatchesSkuOrMetricName(): void
    {
        // Arrange
        $metric = $this->createTestMetric('mtrsearch' . uniqid());
        $productToken = 'prdsearch' . uniqid();
        [$match, $other] = [
            $this->createTestProductAbstract($productToken . '-match'),
            $this->createTestProductAbstract('unrelated-' . uniqid()),
        ];
        // Both are gaps for $metric; the search term should only surface $match.

        $finder = new ProductMetricGapFinder();

        // Act
        $gaps = $finder->findGaps($metric->getIdSearchRankingMetric(), SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, $productToken, 'sku', 'asc', 100, 0);

        // Assert
        $skus = array_column($gaps, 'sku');

        $this->assertSame([$match->getSku()], $skus);
        $this->assertNotContains($other->getSku(), $skus);
    }

    public function testCountGapsIgnoresSearchTermAndCountFilteredGapsApplies(): void
    {
        // Arrange
        $metric = $this->createTestMetric('mtrcount' . uniqid());
        $productToken = 'prdcount' . uniqid();

        $finder = new ProductMetricGapFinder();
        $countBeforeNewProducts = $finder->countGaps(
            $metric->getIdSearchRankingMetric(),
            SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
        );

        $this->createTestProductAbstract($productToken . '-match');
        $this->createTestProductAbstract($productToken . '-other');

        // Act + Assert
        // countGaps ignores search entirely, so it must reflect exactly the two new products added above
        // — a delta rather than an absolute value, since a brand-new metric already counts every REAL
        // catalog product as a gap too (zero coverage is zero coverage), independent of this test.
        $this->assertSame(
            $countBeforeNewProducts + 2,
            $finder->countGaps(
                $metric->getIdSearchRankingMetric(),
                SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
                SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
            ),
        );
        // countFilteredGaps' search term is unique to this test's own fixture, so it stays an exact,
        // catalog-size-independent assertion.
        $this->assertSame(1, $finder->countFilteredGaps(
            $metric->getIdSearchRankingMetric(),
            SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
            $productToken . '-match',
        ));
    }

    public function testSortDirectionIsRespected(): void
    {
        // Arrange
        $metric = $this->createTestMetric('mtrsort' . uniqid());
        $productToken = 'prdsort' . uniqid();
        $this->createTestProductAbstract($productToken . '-aaa');
        $this->createTestProductAbstract($productToken . '-zzz');

        $finder = new ProductMetricGapFinder();

        // Act — scoped to this test's own product token so real catalog rows can't shift which items
        // land within the fetched window between the two calls.
        $ascending = array_column($finder->findGaps($metric->getIdSearchRankingMetric(), SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, $productToken, 'sku', 'asc', 100, 0), 'sku');
        $descending = array_column($finder->findGaps($metric->getIdSearchRankingMetric(), SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, $productToken, 'sku', 'desc', 100, 0), 'sku');

        // Assert
        $this->assertCount(2, $ascending);
        $this->assertSame(array_reverse($ascending), $descending);
    }

    /**
     * The real regression this covers: a product with a value in a DIFFERENT (store, locale) but none in
     * the queried scope must still show as a gap for the queried scope — the LEFT JOIN's ON clause must
     * filter by scope, not a WHERE clause (which would incorrectly hide it, since `product_metric.*` is
     * NULL for genuine no-match rows regardless of which scope was queried).
     */
    public function testGapsAreScopedPerStoreAndLocaleNotSharedAcrossScopes(): void
    {
        // Arrange
        $metric = $this->createTestMetric('mtrscope' . uniqid());
        $productToken = 'prdscope' . uniqid();
        $hasAtDataOnly = $this->createTestProductAbstract($productToken . '-at-only');

        $this->createTestProductMetric($metric, $hasAtDataOnly, 'AT', 'de_AT');

        $finder = new ProductMetricGapFinder();

        // Act
        $deGaps = array_column(
            $finder->findGaps($metric->getIdSearchRankingMetric(), 'DE', 'de_DE', $productToken, 'sku', 'asc', 100, 0),
            'sku',
        );
        $atGaps = array_column(
            $finder->findGaps($metric->getIdSearchRankingMetric(), 'AT', 'de_AT', $productToken, 'sku', 'asc', 100, 0),
            'sku',
        );

        // Assert
        $this->assertContains($hasAtDataOnly->getSku(), $deGaps, 'DE has no value for this product/metric — it must be a real gap for DE.');
        $this->assertNotContains($hasAtDataOnly->getSku(), $atGaps, 'AT genuinely has a value for this product/metric — it must NOT show as a gap for AT.');
    }

    /**
     * An invalid sort column must fall back to a safe default rather than ever being interpolated into
     * the SQL string directly — this is the whitelist that keeps `$sortColumn` (which ultimately traces
     * back to a DataTables request parameter) from becoming a SQL-injection vector.
     */
    public function testAnUnknownSortColumnFallsBackSafelyInsteadOfBeingUsedDirectly(): void
    {
        // Arrange
        $metric = $this->createTestMetric('mtrsafety' . uniqid());
        $productToken = 'prdsafety' . uniqid();
        $this->createTestProductAbstract($productToken . '-check');

        $finder = new ProductMetricGapFinder();

        // Act
        $gaps = $finder->findGaps(
            $metric->getIdSearchRankingMetric(),
            SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
            $productToken,
            'sku; DROP TABLE spy_search_ranking_metric; --',
            'asc; DROP TABLE spy_search_ranking_metric; --',
            100,
            0,
        );

        // Assert
        $this->assertCount(1, $gaps, 'An unrecognized sort column/direction must fall back safely, not error out or execute arbitrary SQL.');
    }

    /**
     * @param string $name
     */
    protected function createTestMetric(string $name): SpySearchRankingMetric
    {
        $metricEntity = new SpySearchRankingMetric();
        $metricEntity->setName('test_' . $name)
            ->setIsHigherBetter(true)
            ->save();

        // "active for the queried scope" now requires a real spy_search_ranking_metric_store_config row
        // (see ProductMetricGapFinder's own Phase-8 docblock note) — seeded for both real stores this
        // file's tests query against (DE by default, AT for the store/locale-scoping regression test).
        foreach (['DE' => 'de_DE', 'AT' => 'de_AT'] as $storeName => $localeName) {
            SpySearchRankingMetricStoreConfigQuery::create()
                ->filterByFkSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
                ->filterByStoreName($storeName)
                ->filterByLocaleName($localeName)
                ->findOneOrCreate()
                ->setFormula('x')
                ->setIsActive(true)
                ->save();
        }

        return $metricEntity;
    }

    /**
     * @param string $sku
     */
    protected function createTestProductAbstract(string $sku): SpyProductAbstract
    {
        $productAbstractEntity = new SpyProductAbstract();
        $productAbstractEntity->setSku($sku)
            ->setAttributes('{}')
            ->save();

        return $productAbstractEntity;
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity
     * @param \Orm\Zed\Product\Persistence\SpyProductAbstract $productAbstractEntity
     * @param string $storeName
     * @param string $localeName
     */
    protected function createTestProductMetric(
        SpySearchRankingMetric $metricEntity,
        SpyProductAbstract $productAbstractEntity,
        string $storeName = SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
        string $localeName = SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
    ): SpySearchRankingProductMetric {
        $productMetricEntity = new SpySearchRankingProductMetric();
        $productMetricEntity->setFkSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
            ->setFkProductAbstract($productAbstractEntity->getIdProductAbstract())
            ->setStoreName($storeName)
            ->setLocaleName($localeName)
            ->setRawValue(5.0)
            ->setNormalizedValue(0.5)
            ->save();

        return $productMetricEntity;
    }

    /**
     * @param array<int, array{id_product_abstract: int, sku: string, missing_metric_name: string}> $gaps
     *
     * @return array<string, array{id_product_abstract: int, sku: string, missing_metric_name: string}>
     */
    protected function indexAllBySku(array $gaps): array
    {
        $indexed = [];

        foreach ($gaps as $gap) {
            $indexed[$gap['sku']] = $gap;
        }

        return $indexed;
    }
}
