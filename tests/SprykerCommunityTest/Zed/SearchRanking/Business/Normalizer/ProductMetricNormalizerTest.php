<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Normalizer;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\MathFunctionProvider;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizer;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Normalizer
 * @group ProductMetricNormalizerTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class ProductMetricNormalizerTest extends Unit
{
    /**
     * @return void
     */
    public function testNormalizesRowsOfActiveMetricAndClampsIntoHalfOpenUnitInterval(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(7)
            ->setName('top_seller')
            ->setWeight(0.5)
            ->setFormula('x / max')
            ->setIsActive(true);

        $repositoryMock = $this->createRepositoryMock($metricTransfer, [
            $this->createProductMetricTransfer(11, 0.0),
            $this->createProductMetricTransfer(12, 250.0),
            $this->createProductMetricTransfer(13, 1000.0),
        ], $this->createStatistics(0.0, 1000.0, 400.0, 3));

        $capturedValues = [];
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('updateNormalizedValues')
            ->willReturnCallback(function (array $values) use (&$capturedValues): void {
                $capturedValues += $values;
            });

        $normalizer = $this->createNormalizer($repositoryMock, $entityManagerMock);

        // Act
        $resultTransfer = $normalizer->normalize();

        // Assert
        $this->assertSame(1, $resultTransfer->getProcessedMetricCount());
        $this->assertSame(3, $resultTransfer->getUpdatedRowCount());
        $this->assertSame([], $resultTransfer->getErrors());

        $config = new SearchRankingConfig();
        $this->assertSame($config->getNormalizedValueMinimum(), $capturedValues[11]);
        $this->assertSame(0.25, $capturedValues[12]);
        $this->assertSame(1.0, $capturedValues[13]);
    }

    /**
     * A metric whose formula cannot be evaluated must be skipped with an error instead of
     * aborting the whole normalization run.
     *
     * @return void
     */
    public function testSkipsMetricWithFailingFormulaAndReportsError(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(9)
            ->setName('broken_metric')
            ->setWeight(0.1)
            ->setFormula('x / max')
            ->setIsActive(true);

        $repositoryMock = $this->createRepositoryMock($metricTransfer, [
            $this->createProductMetricTransfer(21, 5.0, 9),
        ], $this->createStatistics(0.0, 0.0, 0.0, 1));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('updateNormalizedValues');

        $normalizer = $this->createNormalizer($repositoryMock, $entityManagerMock);

        // Act
        $resultTransfer = $normalizer->normalize();

        // Assert
        $this->assertSame(0, $resultTransfer->getProcessedMetricCount());
        $this->assertSame(0, $resultTransfer->getUpdatedRowCount());
        $this->assertCount(1, $resultTransfer->getErrors());
        $this->assertStringContainsString('broken_metric', $resultTransfer->getErrors()[0]);
    }

    /**
     * The random tie-breaker metric (named per {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getRandomMetricName()})
     * is refreshed on its own nightly cadence by `MetricRandomizer` instead — this hourly loop must never
     * touch it, active or not.
     *
     * @return void
     */
    public function testSkipsTheRandomTieBreakerMetricEntirely(): void
    {
        // Arrange
        $metricTransfer = (new SearchRankingMetricTransfer())
            ->setIdSearchRankingMetric(3)
            ->setName('random')
            ->setWeight(0.2)
            ->setFormula('random()')
            ->setIsActive(true);

        $repositoryMock = $this->createRepositoryMock($metricTransfer, [
            $this->createProductMetricTransfer(31, 0.0, 3),
        ], $this->createStatistics(0.0, 0.0, 0.0, 1));

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('updateNormalizedValues');

        $normalizer = $this->createNormalizer($repositoryMock, $entityManagerMock);

        // Act
        $resultTransfer = $normalizer->normalize();

        // Assert
        $this->assertSame(0, $resultTransfer->getProcessedMetricCount());
        $this->assertSame(0, $resultTransfer->getUpdatedRowCount());
        $this->assertSame([], $resultTransfer->getErrors());
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param array<\Generated\Shared\Transfer\SearchRankingProductMetricTransfer> $productMetricTransfers
     * @param \Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer $statisticsTransfer
     *
     * @return \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface
     */
    protected function createRepositoryMock(
        SearchRankingMetricTransfer $metricTransfer,
        array $productMetricTransfers,
        SearchRankingMetricStatisticsTransfer $statisticsTransfer,
    ): SearchRankingRepositoryInterface {
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getActiveMetricCollection')->willReturn(
            (new SearchRankingMetricCollectionTransfer())->addMetric($metricTransfer),
        );
        $repositoryMock->method('getMetricStatistics')->willReturn($statisticsTransfer);
        $repositoryMock->method('getProductMetricBatch')->willReturnCallback(
            function (int $idMetric, int $idLast) use ($productMetricTransfers): array {
                return array_values(array_filter(
                    $productMetricTransfers,
                    function (SearchRankingProductMetricTransfer $productMetricTransfer) use ($idMetric, $idLast): bool {
                        return $productMetricTransfer->getFkSearchRankingMetric() === $idMetric
                            && $productMetricTransfer->getIdSearchRankingProductMetric() > $idLast;
                    },
                ));
            },
        );

        return $repositoryMock;
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     *
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizer
     */
    protected function createNormalizer(
        SearchRankingRepositoryInterface $repository,
        SearchRankingEntityManagerInterface $entityManager,
    ): ProductMetricNormalizer {
        $config = new SearchRankingConfig();

        return new ProductMetricNormalizer(
            $repository,
            $entityManager,
            new FormulaEvaluator(new MathFunctionProvider(), $config),
            $config,
        );
    }

    /**
     * @param int $idSearchRankingProductMetric
     * @param float $rawValue
     * @param int $fkSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingProductMetricTransfer
     */
    protected function createProductMetricTransfer(
        int $idSearchRankingProductMetric,
        float $rawValue,
        int $fkSearchRankingMetric = 7,
    ): SearchRankingProductMetricTransfer {
        return (new SearchRankingProductMetricTransfer())
            ->setIdSearchRankingProductMetric($idSearchRankingProductMetric)
            ->setFkSearchRankingMetric($fkSearchRankingMetric)
            ->setFkProductAbstract(100 + $idSearchRankingProductMetric)
            ->setRawValue($rawValue);
    }

    /**
     * @param float $min
     * @param float $max
     * @param float $avg
     * @param int $count
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricStatisticsTransfer
     */
    protected function createStatistics(float $min, float $max, float $avg, int $count): SearchRankingMetricStatisticsTransfer
    {
        return (new SearchRankingMetricStatisticsTransfer())
            ->setMinValue($min)
            ->setMaxValue($max)
            ->setAvgValue($avg)
            ->setCount($count);
    }
}
