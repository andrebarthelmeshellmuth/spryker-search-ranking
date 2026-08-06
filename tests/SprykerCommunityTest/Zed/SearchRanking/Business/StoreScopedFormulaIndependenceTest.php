<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business;

use Codeception\Test\Unit;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfigQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSet;
use Spryker\Zed\Store\Business\StoreFacade;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\MathFunctionProvider;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizer;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManager;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepository;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\DataSet\SearchRankingMetricDataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\SearchRankingMetricWriterStep;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\SearchRankingProductMetricWriterStep;

/**
 * INTEGRATION TEST — permanent automated coverage for the store-scoped-formula migration's own central
 * claim (see project memory), previously only ever proven by hand (live chrome-devtools MCP + direct DB
 * queries against the real running demoshop, Phase 3 of that migration): the SAME metric can carry a
 * GENUINELY DIFFERENT formula per store and produce genuinely different normalized output for the exact
 * same raw input, end to end through the REAL {@see ProductMetricNormalizer::normalize()} entry point
 * (not {@see ProductMetricNormalizer::normalizeMetric()} directly, which takes its formula as a plain
 * argument and would prove nothing about the store-scoped LOOKUP this test exists to cover) — so this
 * exercises the real {@see SearchRankingRepository::getActiveMetricCollection()} store-scoped read too,
 * not just the normalization math {@see \SprykerCommunityTest\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerTest}
 * already covers with mocks.
 *
 * DE's formula (`atan(x / 2241.481486) / (pi() / 2)`) and the raw value (23105) are the EXACT real values
 * this migration's own live Phase 3 verification used — reusing them here means this test's expected
 * outputs are independently hand-verified twice, once live against the real demoshop and once here.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group StoreScopedFormulaIndependenceTest
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class StoreScopedFormulaIndependenceTest extends Unit
{
    /**
     * @var string
     */
    protected const METRIC_NAME = 'zz_store_scope_independence_test_metric';

    /**
     * @var string
     */
    protected const DE_FORMULA = 'atan(x / 2241.481486) / (pi() / 2)';

    /**
     * @var string
     */
    protected const AT_FORMULA = 'x / (x + 5000)';

    /**
     * @var float
     */
    protected const RAW_VALUE = 23105.0;

    /**
     * @var int
     */
    protected const ID_PRODUCT_ABSTRACT = 9;

    public function testTheSameMetricNormalizesTheSameRawValueDifferentlyPerStoreUsingEachStoresOwnRealFormula(): void
    {
        // Arrange — one real metric, one real store-config row per store with a DELIBERATELY different
        // formula, the identical raw value imported for both stores.
        $idMetric = $this->importMetric();
        $this->setStoreFormula($idMetric, 'DE', static::DE_FORMULA);
        $this->setStoreFormula($idMetric, 'AT', static::AT_FORMULA);
        $this->importProductMetric($idMetric, 'DE', 'de_DE');
        $this->importProductMetric($idMetric, 'AT', 'de_DE');

        // Act — the real per-store normalize() entry point, which looks the formula up itself via the
        // real store-scoped repository read, once per store.
        $normalizer = $this->createNormalizer();
        $normalizer->normalize('DE', 'de_DE');
        $normalizer->normalize('AT', 'de_DE');

        // Assert — same metric, same raw input, genuinely different output per store, matching each
        // store's own real formula by hand: atan(23105 / 2241.481486) / (pi() / 2) ≈ 0.9384;
        // 23105 / (23105 + 5000) ≈ 0.8220. Independently re-read from the DB, not just trusting the
        // normalizer's own return value.
        $deNormalizedValue = $this->findNormalizedValue($idMetric, 'DE', 'de_DE');
        $atNormalizedValue = $this->findNormalizedValue($idMetric, 'AT', 'de_DE');

        $this->assertEqualsWithDelta(0.9384, $deNormalizedValue, 0.001, 'DE must normalize via its own atan formula.');
        $this->assertEqualsWithDelta(0.8220, $atNormalizedValue, 0.001, 'AT must normalize via its own hyperbolic formula, not DE\'s.');
        $this->assertNotEqualsWithDelta($deNormalizedValue, $atNormalizedValue, 0.01, 'The same metric, same raw value, must genuinely diverge per store — the whole point of the migration this test covers.');
    }

    protected function importMetric(): int
    {
        (new SearchRankingMetricWriterStep())->execute(new DataSet([
            SearchRankingMetricDataSetInterface::COL_NAME => static::METRIC_NAME,
            SearchRankingMetricDataSetInterface::COL_FORMULA => static::DE_FORMULA,
            SearchRankingMetricDataSetInterface::COL_IS_ACTIVE => true,
            SearchRankingMetricDataSetInterface::COL_STORE => 'DE',
            SearchRankingMetricDataSetInterface::COL_LOCALE => 'de_DE',
            SearchRankingMetricDataSetInterface::COL_WEIGHT => 0.5,
        ]));

        $metricEntity = SpySearchRankingMetricQuery::create()->filterByName(static::METRIC_NAME)->findOne();
        $this->assertNotNull($metricEntity, 'Setup: the metric must have actually been imported before the rest of the test can run.');

        return $metricEntity->getIdSearchRankingMetric();
    }

    /**
     * @param int $idMetric
     * @param string $storeName
     * @param string $formula
     */
    protected function setStoreFormula(int $idMetric, string $storeName, string $formula): void
    {
        SpySearchRankingMetricStoreConfigQuery::create()
            ->filterByFkSearchRankingMetric($idMetric)
            ->filterByStoreName($storeName)
            ->findOneOrCreate()
            ->setFormula($formula)
            ->setIsActive(true)
            ->save();
    }

    /**
     * @param int $idMetric
     * @param string $storeName
     * @param string $localeName
     */
    protected function importProductMetric(int $idMetric, string $storeName, string $localeName): void
    {
        (new SearchRankingProductMetricWriterStep())->execute(new DataSet([
            SearchRankingProductMetricDataSetInterface::KEY_ID_SEARCH_RANKING_METRIC => $idMetric,
            SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT => static::ID_PRODUCT_ABSTRACT,
            SearchRankingProductMetricDataSetInterface::COL_RAW_VALUE => static::RAW_VALUE,
            SearchRankingProductMetricDataSetInterface::COL_STORE => $storeName,
            SearchRankingProductMetricDataSetInterface::COL_LOCALE => $localeName,
        ]));
    }

    /**
     * @param int $idMetric
     * @param string $storeName
     * @param string $localeName
     */
    protected function findNormalizedValue(int $idMetric, string $storeName, string $localeName): ?float
    {
        $productMetricEntity = SpySearchRankingProductMetricQuery::create()
            ->filterByFkSearchRankingMetric($idMetric)
            ->filterByFkProductAbstract(static::ID_PRODUCT_ABSTRACT)
            ->filterByStoreName($storeName)
            ->filterByLocaleName($localeName)
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
