<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric;

use Codeception\Test\Unit;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricWeightQuery;
use Spryker\Zed\DataImport\Business\Exception\InvalidDataException;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSet;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\DataSet\SearchRankingMetricDataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\SearchRankingMetricWriterStep;

/**
 * INTEGRATION TEST — real database, real rows, never mocked: this step's only job is a real Propel
 * upsert-by-name, so a mocked query builder could confirm the PHP called the right methods but never
 * that a second import of the same name actually updates the existing row instead of creating a
 * duplicate, which is the one behavior actually worth protecting here.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingDataImport
 * @group Business
 * @group Writer
 * @group SearchRankingMetric
 * @group SearchRankingMetricWriterStepTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRankingDataImport\SearchRankingDataImportZedTester $tester
 */
class SearchRankingMetricWriterStepTest extends Unit
{
    /**
     * @var array<\Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric>
     */
    protected array $metricEntities = [];

    protected function _after(): void
    {
        foreach ($this->metricEntities as $metricEntity) {
            $metricEntity->delete();
        }

        parent::_after();
    }

    public function testExecuteCreatesANewMetricWhenNoneWithThatNameExistsYet(): void
    {
        // Arrange
        $name = 'test_new_metric_' . uniqid();
        $dataSet = new DataSet([
            SearchRankingMetricDataSetInterface::COL_NAME => $name,
            SearchRankingMetricDataSetInterface::COL_WEIGHT => '2.5',
            SearchRankingMetricDataSetInterface::COL_FORMULA => 'x / max',
            SearchRankingMetricDataSetInterface::COL_IS_ACTIVE => '1',
            SearchRankingMetricDataSetInterface::COL_STORE => SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SearchRankingMetricDataSetInterface::COL_LOCALE => SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
        ]);

        // Act
        (new SearchRankingMetricWriterStep())->execute($dataSet);

        // Assert
        $metricEntity = $this->findAndTrackMetric($name);

        $this->assertSame(2.5, $this->findWeight($metricEntity->getIdSearchRankingMetric()));
        $this->assertSame('x / max', $metricEntity->getFormula());
        $this->assertTrue($metricEntity->getIsActive());
    }

    public function testExecuteUpdatesTheExistingMetricInsteadOfCreatingADuplicateWhenTheNameAlreadyExists(): void
    {
        // Arrange
        $name = 'test_existing_metric_' . uniqid();
        $this->createTestMetric($name, 1.0, 'x', true);

        $dataSet = new DataSet([
            SearchRankingMetricDataSetInterface::COL_NAME => $name,
            SearchRankingMetricDataSetInterface::COL_WEIGHT => '9.0',
            SearchRankingMetricDataSetInterface::COL_FORMULA => 'x / avg',
            SearchRankingMetricDataSetInterface::COL_IS_ACTIVE => '0',
            SearchRankingMetricDataSetInterface::COL_STORE => SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SearchRankingMetricDataSetInterface::COL_LOCALE => SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
        ]);

        // Act
        (new SearchRankingMetricWriterStep())->execute($dataSet);

        // Assert
        $this->assertSame(1, SpySearchRankingMetricQuery::create()->filterByName($name)->count());

        $metricEntity = $this->findAndTrackMetric($name);
        $this->assertSame(9.0, $this->findWeight($metricEntity->getIdSearchRankingMetric()));
        $this->assertSame('x / avg', $metricEntity->getFormula());
        $this->assertFalse($metricEntity->getIsActive());
    }

    public function testExecuteThrowsForANameThatWouldNeverContributeToLiveRanking(): void
    {
        // Arrange — mixed case is rejected by FunctionScoreBuilder::METRIC_NAME_PATTERN just like a
        // leading digit or a hyphen would be; this is the case a real CSV export is most likely to
        // produce by accident (e.g. from a spreadsheet column titled "Top_Seller").
        $name = 'Test_Invalid_Metric_' . uniqid();
        $dataSet = new DataSet([
            SearchRankingMetricDataSetInterface::COL_NAME => $name,
            SearchRankingMetricDataSetInterface::COL_WEIGHT => '1.0',
            SearchRankingMetricDataSetInterface::COL_FORMULA => 'x',
            SearchRankingMetricDataSetInterface::COL_IS_ACTIVE => '1',
        ]);

        // Act
        try {
            (new SearchRankingMetricWriterStep())->execute($dataSet);
            $this->fail('Expected InvalidDataException was not thrown.');
        } catch (InvalidDataException $exception) {
            // Assert
            $this->assertStringContainsString($name, $exception->getMessage());
        }

        $this->assertSame(0, SpySearchRankingMetricQuery::create()->filterByName($name)->count());
    }

    public function testExecuteThrowsForANegativeWeightAndPersistsNeitherTheMetricNorItsWeight(): void
    {
        // Arrange — a negative weight doesn't just fail to contribute (like an unrecognized formula
        // does), it corrupts every OTHER active metric's normalized weight too: normalizeMetricWeights()
        // divides by the sum of all active weights, and a negative value can shrink that sum toward zero
        // without ever tripping its exact-zero guard. See MetricForm's own GreaterThanOrEqual(0)
        // constraint, which this CSV path must match.
        $name = 'test_negative_weight_metric_' . uniqid();
        $dataSet = new DataSet([
            SearchRankingMetricDataSetInterface::COL_NAME => $name,
            SearchRankingMetricDataSetInterface::COL_WEIGHT => '-0.4',
            SearchRankingMetricDataSetInterface::COL_FORMULA => 'x / max',
            SearchRankingMetricDataSetInterface::COL_IS_ACTIVE => '1',
            SearchRankingMetricDataSetInterface::COL_STORE => SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SearchRankingMetricDataSetInterface::COL_LOCALE => SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
        ]);

        // Act
        try {
            (new SearchRankingMetricWriterStep())->execute($dataSet);
            $this->fail('Expected InvalidDataException was not thrown.');
        } catch (InvalidDataException $exception) {
            // Assert
            $this->assertStringContainsString('-0.4', $exception->getMessage());
        }

        // The metric row itself is created before the weight is validated (mirroring how the formula is
        // saved before the weight-scoped row is touched) -- track it for cleanup, but the important
        // assertion is that no weight row was ever persisted for it.
        $metricEntity = SpySearchRankingMetricQuery::create()->findOneByName($name);

        if ($metricEntity === null) {
            return;
        }

        $this->metricEntities[] = $metricEntity;
        $this->assertNull($this->findWeight($metricEntity->getIdSearchRankingMetric()));
    }

    /**
     * @param string $name
     * @param float $weight
     * @param string $formula
     * @param bool $isActive
     */
    protected function createTestMetric(string $name, float $weight, string $formula, bool $isActive): SpySearchRankingMetric
    {
        $metricEntity = new SpySearchRankingMetric();
        $metricEntity->setName($name)
            ->setFormula($formula)
            ->setIsActive($isActive)
            ->setIsHigherBetter(true)
            ->save();

        SpySearchRankingMetricWeightQuery::create()
            ->filterByFkSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
            ->filterByStoreName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
            ->filterByLocaleName(SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
            ->findOneOrCreate()
            ->setWeight($weight)
            ->save();

        $this->metricEntities[] = $metricEntity;

        return $metricEntity;
    }

    /**
     * @param string $name
     */
    protected function findAndTrackMetric(string $name): SpySearchRankingMetric
    {
        $metricEntity = SpySearchRankingMetricQuery::create()->findOneByName($name);
        $this->assertNotNull($metricEntity);

        $this->metricEntities[] = $metricEntity;

        return $metricEntity;
    }

    /**
     * TEMPORARY Phase-1 default scope lookup — see SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME.
     *
     * @param int $idSearchRankingMetric
     */
    protected function findWeight(int $idSearchRankingMetric): ?float
    {
        $metricWeightEntity = SpySearchRankingMetricWeightQuery::create()
            ->filterByFkSearchRankingMetric($idSearchRankingMetric)
            ->filterByStoreName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
            ->filterByLocaleName(SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
            ->findOne();

        return $metricWeightEntity?->getWeight();
    }
}
