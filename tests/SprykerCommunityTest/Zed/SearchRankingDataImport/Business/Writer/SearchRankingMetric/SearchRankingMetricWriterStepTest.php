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
use Spryker\Zed\DataImport\Business\Exception\InvalidDataException;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSet;
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

    /**
     * @return void
     */
    protected function _after(): void
    {
        foreach ($this->metricEntities as $metricEntity) {
            $metricEntity->delete();
        }

        parent::_after();
    }

    /**
     * @return void
     */
    public function testExecuteCreatesANewMetricWhenNoneWithThatNameExistsYet(): void
    {
        // Arrange
        $name = 'test_new_metric_' . uniqid();
        $dataSet = new DataSet([
            SearchRankingMetricDataSetInterface::COL_NAME => $name,
            SearchRankingMetricDataSetInterface::COL_WEIGHT => '2.5',
            SearchRankingMetricDataSetInterface::COL_FORMULA => 'x / max',
            SearchRankingMetricDataSetInterface::COL_IS_ACTIVE => '1',
        ]);

        // Act
        (new SearchRankingMetricWriterStep())->execute($dataSet);

        // Assert
        $metricEntity = $this->findAndTrackMetric($name);

        $this->assertSame(2.5, $metricEntity->getWeight());
        $this->assertSame('x / max', $metricEntity->getFormula());
        $this->assertTrue($metricEntity->getIsActive());
    }

    /**
     * @return void
     */
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
        ]);

        // Act
        (new SearchRankingMetricWriterStep())->execute($dataSet);

        // Assert
        $this->assertSame(1, SpySearchRankingMetricQuery::create()->filterByName($name)->count());

        $metricEntity = $this->findAndTrackMetric($name);
        $this->assertSame(9.0, $metricEntity->getWeight());
        $this->assertSame('x / avg', $metricEntity->getFormula());
        $this->assertFalse($metricEntity->getIsActive());
    }

    /**
     * @return void
     */
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

    /**
     * @param string $name
     * @param float $weight
     * @param string $formula
     * @param bool $isActive
     *
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric
     */
    protected function createTestMetric(string $name, float $weight, string $formula, bool $isActive): SpySearchRankingMetric
    {
        $metricEntity = new SpySearchRankingMetric();
        $metricEntity->setName($name)
            ->setWeight($weight)
            ->setFormula($formula)
            ->setIsActive($isActive)
            ->setIsHigherBetter(true)
            ->save();

        $this->metricEntities[] = $metricEntity;

        return $metricEntity;
    }

    /**
     * @param string $name
     *
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric
     */
    protected function findAndTrackMetric(string $name): SpySearchRankingMetric
    {
        $metricEntity = SpySearchRankingMetricQuery::create()->findOneByName($name);
        $this->assertNotNull($metricEntity);

        $this->metricEntities[] = $metricEntity;

        return $metricEntity;
    }
}
