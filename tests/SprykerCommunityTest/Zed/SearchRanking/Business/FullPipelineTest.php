<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ProductPageLoadTransfer;
use Generated\Shared\Transfer\ProductPageSearchTransfer;
use Generated\Shared\Transfer\ProductPayloadTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Shared\ProductPageSearch\ProductPageSearchConfig;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSet;
use Spryker\Zed\Store\Business\StoreFacade;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\MathFunctionProvider;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizer;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoader;
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingScoresDataExpanderPlugin;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManager;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepository;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\DataSet\SearchRankingMetricDataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\SearchRankingMetricWriterStep;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\SearchRankingProductMetricWriterStep;

/**
 * INTEGRATION TEST — the real import -> normalize -> export-shape pipeline, chained through the ACTUAL
 * production classes with no mocks, stopping at the same queue/consumer boundary
 * {@see \SprykerCommunityTest\Zed\SearchRankingStorage\Communication\Plugin\Synchronization\SearchRankingConfigurationSynchronizationDataPluginTest}
 * already stops at, for the identical reason: what happens between a queue message and Elasticsearch is
 * Spryker core's own `publish` / `search-elasticsearch` responsibility, not this package's.
 *
 * Chain proven here, each link a real class already individually unit-tested elsewhere with mocks — this
 * test's only job is to prove the links actually connect:
 *
 * 1. {@see SearchRankingMetricWriterStep} / {@see SearchRankingProductMetricWriterStep} (DataImport, real
 *    Propel writes) — import one metric and two product-metric rows for two real product abstracts
 *    (ids 9 "Besucherstuhl" and 62 "Konferenzstuhl", the same known-real ids
 *    {@see \SprykerCommunityTest\Client\SearchRankingOptimizer\Search\RankEvalRunnerTest} in the sibling
 *    optimizer package already relies on).
 * 2. {@see ProductMetricNormalizer::normalizeMetric()} (real repository read/write) — normalizes those two
 *    rows via a real formula, exercised directly rather than through
 *    {@see \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade::normalizeProductMetricValues()}'s
 *    store/locale fan-out loop, since that loop already has full, dedicated coverage in
 *    {@see \SprykerCommunityTest\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerTest} — going
 *    through it here would only re-prove the loop, not the DB round trip this test actually exists for.
 * 3. {@see ScoresPageDataLoader} (real repository read) — loads the normalized value back onto a
 *    `ProductPayloadTransfer`.
 * 4. {@see SearchRankingScoresDataExpanderPlugin} (real, stateless) — copies that payload's scores onto the
 *    `ProductPageSearchTransfer`, which is exactly the page-document `scores` fragment that ships to
 *    Elasticsearch once core's own publish/synchronize pipeline picks it up.
 *
 * Uses a fresh, uniquely-named metric (`METRIC_NAME`) so statistics (`MIN`/`MAX`/`AVG` over rows for THIS
 * metric id) can never be polluted by the real CSV-imported fixture metrics (`top_seller` etc.) that also
 * have rows against the same two product abstracts — assertions below check only this test's own key in
 * the resulting scores maps, not the whole array, since real metrics' scores are legitimately present too.
 * `TransactionHelper` (added to this suite's own `codeception.yml` alongside this test — like
 * `SearchRankingStorage`'s suite before it, `SearchRanking`'s own suite had no Propel bootstrap at all
 * because none of its existing tests ran a real Propel query) wraps every test in a transaction and rolls
 * it back afterwards, so no manual cleanup is needed.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group FullPipelineTest
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group NeedsDatabase
 */
class FullPipelineTest extends Unit
{
    /**
     * @var string
     */
    protected const METRIC_NAME = 'zz_full_pipeline_test_metric';

    /**
     * @var string
     */
    protected const STORE_NAME = 'DE';

    /**
     * @var string
     */
    protected const LOCALE_NAME = 'de_DE';

    /**
     * @var int
     */
    protected const ID_PRODUCT_ABSTRACT_BESUCHERSTUHL = 9;

    /**
     * @var int
     */
    protected const ID_PRODUCT_ABSTRACT_KONFERENZSTUHL = 62;

    public function testImportedRowsFlowThroughNormalizationIntoThePageDocumentScoresFragment(): void
    {
        // Arrange — import: real DataImport writer steps, real Propel writes.
        $idMetric = $this->importMetric();
        $this->importProductMetric($idMetric, static::ID_PRODUCT_ABSTRACT_BESUCHERSTUHL, 100.0);
        $this->importProductMetric($idMetric, static::ID_PRODUCT_ABSTRACT_KONFERENZSTUHL, 400.0);

        // Act — normalize: real ProductMetricNormalizer, real formula evaluation, real Propel update.
        $updatedRowCount = $this->createNormalizer()->normalizeMetric(
            (new SearchRankingMetricTransfer())->setIdSearchRankingMetric($idMetric)->setFormula('x / max'),
            static::STORE_NAME,
            static::LOCALE_NAME,
        );

        // Assert — normalize: min=100/max=400 -> 0.25; max row normalizes to 1.0.
        $this->assertSame(2, $updatedRowCount);

        // Independently re-read from the DB — not just trusting the normalizer's own return value.
        $this->assertSame(0.25, $this->findNormalizedValue($idMetric, static::ID_PRODUCT_ABSTRACT_BESUCHERSTUHL));
        $this->assertSame(1.0, $this->findNormalizedValue($idMetric, static::ID_PRODUCT_ABSTRACT_KONFERENZSTUHL));

        // Act — export-shape: real ScoresPageDataLoader loads the normalized value onto a payload transfer.
        $productPageLoadTransfer = (new ProductPageLoadTransfer())
            ->setProductAbstractIds([static::ID_PRODUCT_ABSTRACT_BESUCHERSTUHL])
            ->setPayloadTransfers([
                (new ProductPayloadTransfer())->setIdProductAbstract(static::ID_PRODUCT_ABSTRACT_BESUCHERSTUHL),
            ]);

        $loadedPayloadTransfer = (new ScoresPageDataLoader(new SearchRankingRepository()))
            ->expandProductPageLoadTransfer($productPageLoadTransfer)
            ->getPayloadTransfers()[0];

        $loadedScores = $loadedPayloadTransfer->getSearchRankingScores();
        $this->assertArrayHasKey(static::METRIC_NAME, $loadedScores, 'The freshly normalized metric must be present among the product\'s scores.');
        $this->assertSame(0.25, $loadedScores[static::METRIC_NAME]);

        // Act — export-shape: real SearchRankingScoresDataExpanderPlugin copies the payload onto the page
        // document transfer — exactly the shape core's own publish pipeline picks up next.
        $productData = [ProductPageSearchConfig::PRODUCT_ABSTRACT_PAGE_LOAD_DATA => $loadedPayloadTransfer];
        $productAbstractPageSearchTransfer = new ProductPageSearchTransfer();

        (new SearchRankingScoresDataExpanderPlugin())->expandProductPageData($productData, $productAbstractPageSearchTransfer);

        // Assert — export-shape: the page-document `scores` fragment carries the value all the way through.
        $exportedScores = $productAbstractPageSearchTransfer->getScores();
        $this->assertArrayHasKey(static::METRIC_NAME, $exportedScores);
        $this->assertSame(0.25, $exportedScores[static::METRIC_NAME]);
    }

    protected function importMetric(): int
    {
        (new SearchRankingMetricWriterStep())->execute(new DataSet([
            SearchRankingMetricDataSetInterface::COL_NAME => static::METRIC_NAME,
            SearchRankingMetricDataSetInterface::COL_FORMULA => 'x / max',
            SearchRankingMetricDataSetInterface::COL_IS_ACTIVE => true,
            SearchRankingMetricDataSetInterface::COL_STORE => static::STORE_NAME,
            SearchRankingMetricDataSetInterface::COL_LOCALE => static::LOCALE_NAME,
            SearchRankingMetricDataSetInterface::COL_WEIGHT => 0.5,
        ]));

        $metricEntity = SpySearchRankingMetricQuery::create()->filterByName(static::METRIC_NAME)->findOne();
        $this->assertNotNull($metricEntity, 'Setup: the metric must have actually been imported before the rest of the pipeline can run.');

        return $metricEntity->getIdSearchRankingMetric();
    }

    /**
     * @param int $idMetric
     * @param int $idProductAbstract
     * @param float $rawValue
     */
    protected function importProductMetric(int $idMetric, int $idProductAbstract, float $rawValue): void
    {
        (new SearchRankingProductMetricWriterStep())->execute(new DataSet([
            SearchRankingProductMetricDataSetInterface::KEY_ID_SEARCH_RANKING_METRIC => $idMetric,
            SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT => $idProductAbstract,
            SearchRankingProductMetricDataSetInterface::COL_RAW_VALUE => $rawValue,
            SearchRankingProductMetricDataSetInterface::COL_STORE => static::STORE_NAME,
            SearchRankingProductMetricDataSetInterface::COL_LOCALE => static::LOCALE_NAME,
        ]));
    }

    /**
     * @param int $idMetric
     * @param int $idProductAbstract
     */
    protected function findNormalizedValue(int $idMetric, int $idProductAbstract): ?float
    {
        $productMetricEntity = SpySearchRankingProductMetricQuery::create()
            ->filterByFkSearchRankingMetric($idMetric)
            ->filterByFkProductAbstract($idProductAbstract)
            ->filterByStoreName(static::STORE_NAME)
            ->filterByLocaleName(static::LOCALE_NAME)
            ->findOne();
        $this->assertNotNull($productMetricEntity, 'Setup: the product-metric row must have actually been imported.');

        return $productMetricEntity->getNormalizedValue();
    }

    protected function createNormalizer(): ProductMetricNormalizer
    {
        $config = new SearchRankingConfig();

        return new ProductMetricNormalizer(
            new SearchRankingRepository(),
            new SearchRankingEntityManager(),
            new FormulaEvaluator(new MathFunctionProvider(), $config),
            $config,
            new SearchRankingToStoreFacadeBridge(new StoreFacade()),
        );
    }
}
