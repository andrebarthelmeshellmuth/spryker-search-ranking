<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric;

use Codeception\Test\Unit;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Spryker\Zed\DataImport\Business\Exception\EntityNotFoundException;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSet;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\MetricNameToIdSearchRankingMetricStep;

/**
 * INTEGRATION TEST — real database, real rows, never mocked: resolving a real metric name to a real
 * primary key is the entire job of this step, so a mocked query could only confirm the PHP called the
 * right method, never that the resolved ID is actually correct or that a genuinely missing name is
 * actually rejected before a product-metric row can be written against a foreign key that doesn't exist.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingDataImport
 * @group Business
 * @group Writer
 * @group SearchRankingProductMetric
 * @group MetricNameToIdSearchRankingMetricStepTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRankingDataImport\SearchRankingDataImportZedTester $tester
 */
class MetricNameToIdSearchRankingMetricStepTest extends Unit
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
    public function testExecuteResolvesAnExistingMetricNameToItsRealId(): void
    {
        // Arrange
        $metricEntity = $this->createTestMetric('test_resolvable_metric_' . uniqid());
        $dataSet = new DataSet([
            SearchRankingProductMetricDataSetInterface::COL_METRIC_NAME => $metricEntity->getName(),
        ]);

        // Act
        (new MetricNameToIdSearchRankingMetricStep())->execute($dataSet);

        // Assert
        $this->assertSame(
            $metricEntity->getIdSearchRankingMetric(),
            $dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_SEARCH_RANKING_METRIC],
        );
    }

    /**
     * @return void
     */
    public function testExecuteThrowsWhenTheMetricNameDoesNotExistYet(): void
    {
        // Arrange — README's own documented convention: "Import metrics first."
        $dataSet = new DataSet([
            SearchRankingProductMetricDataSetInterface::COL_METRIC_NAME => 'test_never_imported_' . uniqid(),
        ]);

        // Act & Assert
        $this->expectException(EntityNotFoundException::class);

        (new MetricNameToIdSearchRankingMetricStep())->execute($dataSet);
    }

    /**
     * @param string $name
     *
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric
     */
    protected function createTestMetric(string $name): SpySearchRankingMetric
    {
        $metricEntity = new SpySearchRankingMetric();
        $metricEntity->setName($name)
            ->setWeight(1.0)
            ->setFormula('x')
            ->setIsActive(true)
            ->setIsHigherBetter(true)
            ->save();

        $this->metricEntities[] = $metricEntity;

        return $metricEntity;
    }
}
