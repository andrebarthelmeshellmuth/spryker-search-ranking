<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Persistence;

use Codeception\Test\Unit;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfigQuery;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepository;

/**
 * INTEGRATION TEST — real Propel reads, no mocked repository. Covers
 * {@see SearchRankingRepository::attachStoreConfig()}'s "safe absence" contract: a metric with no
 * `spy_search_ranking_metric_store_config` row for a given store (never configured there — e.g. a
 * brand-new market before its "Sync store configuration" ever ran) must come back with `formula=null`/
 * `isActive=false`/`shape=null`, not an error and not a stale/defaulted value. Nothing else in this
 * package proved that end to end against a real database before — every other test either mocks
 * {@see \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface} directly or
 * only exercises a store that DOES have a store-config row. This exact absence is what
 * {@see \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriter::saveMetricWeightForLocale()},
 * {@see \SprykerCommunity\Zed\SearchRanking\Business\Fitting\CurrentMetricFitEvaluator::evaluate()}, and
 * {@see \SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapFinder} all depend on staying
 * true — a regression here would silently break all three.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Persistence
 * @group SearchRankingRepositoryTest
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class SearchRankingRepositoryTest extends Unit
{
    /**
     * @var string
     */
    protected const METRIC_NAME = 'zz_repository_store_config_absence_test_metric';

    /**
     * @var string
     */
    protected const CONFIGURED_STORE_NAME = 'DE';

    /**
     * @var string
     */
    protected const UNCONFIGURED_STORE_NAME = 'AT';

    /**
     * @var string
     */
    protected const REAL_FORMULA = 'x / max';

    public function testFindMetricByIdReturnsANullFormulaAndInactiveForAStoreWithNoStoreConfigRow(): void
    {
        // Arrange — one real metric, with a real store-config row for DE only; AT never configured.
        $idMetric = $this->createMetric();
        $this->createStoreConfig($idMetric, static::CONFIGURED_STORE_NAME, static::REAL_FORMULA);

        // Act
        $repository = new SearchRankingRepository();
        $unconfiguredMetricTransfer = $repository->findMetricById($idMetric, static::UNCONFIGURED_STORE_NAME, 'de_DE');
        $configuredMetricTransfer = $repository->findMetricById($idMetric, static::CONFIGURED_STORE_NAME, 'de_DE');

        // Assert — the unconfigured store is a SAFE absence (null formula, inactive), not an error and
        // not the configured store's formula leaking across.
        $this->assertNotNull($unconfiguredMetricTransfer);
        $this->assertNull($unconfiguredMetricTransfer->getFormula());
        $this->assertFalse($unconfiguredMetricTransfer->getIsActive());
        $this->assertNull($unconfiguredMetricTransfer->getShape());

        // Positive control: the configured store's own real store-config row IS returned, proving the
        // method isn't just always returning null regardless of what's in the database.
        $this->assertNotNull($configuredMetricTransfer);
        $this->assertSame(static::REAL_FORMULA, $configuredMetricTransfer->getFormula());
        $this->assertTrue($configuredMetricTransfer->getIsActive());
    }

    public function testGetMetricCollectionReturnsANullFormulaAndInactiveForAStoreWithNoStoreConfigRow(): void
    {
        // Arrange
        $idMetric = $this->createMetric();
        $this->createStoreConfig($idMetric, static::CONFIGURED_STORE_NAME, static::REAL_FORMULA);

        // Act
        $metricCollectionTransfer = (new SearchRankingRepository())->getMetricCollection(static::UNCONFIGURED_STORE_NAME);

        $metricTransfer = null;

        foreach ($metricCollectionTransfer->getMetrics() as $candidateMetricTransfer) {
            if ($candidateMetricTransfer->getIdSearchRankingMetric() !== $idMetric) {
                continue;
            }

            $metricTransfer = $candidateMetricTransfer;
        }

        // Assert
        $this->assertNotNull($metricTransfer, 'Setup: the metric must appear in the collection even for a store it was never configured for.');
        $this->assertNull($metricTransfer->getFormula());
        $this->assertFalse($metricTransfer->getIsActive());
    }

    protected function createMetric(): int
    {
        $metricEntity = SpySearchRankingMetricQuery::create()
            ->filterByName(static::METRIC_NAME)
            ->findOneOrCreate();
        $metricEntity->setIsHigherBetter(true);
        $metricEntity->setIsLocaleScoped(true);
        $metricEntity->save();

        return $metricEntity->getIdSearchRankingMetric();
    }

    /**
     * @param int $idMetric
     * @param string $storeName
     * @param string $formula
     */
    protected function createStoreConfig(int $idMetric, string $storeName, string $formula): void
    {
        SpySearchRankingMetricStoreConfigQuery::create()
            ->filterByFkSearchRankingMetric($idMetric)
            ->filterByStoreName($storeName)
            ->findOneOrCreate()
            ->setFormula($formula)
            ->setIsActive(true)
            ->save();
    }
}
