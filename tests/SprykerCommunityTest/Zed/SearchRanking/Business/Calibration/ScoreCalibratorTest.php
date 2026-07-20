<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Calibration;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer;
use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
use RuntimeException;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\Calibration\ScoreCalibrator;
use SprykerCommunity\Zed\SearchRanking\Business\Calibration\StatisticsCalculatorInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Calibration
 * @group ScoreCalibratorTest
 * Add your own group annotations below this line
 */
class ScoreCalibratorTest extends Unit
{
    /**
     * @return void
     */
    public function testReturnsNullWhenThereIsNoUploadedCalibration(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getUploadedCalibrations')->willReturn([]);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('updateCalibrationStatus');

        $calibrator = new ScoreCalibrator(
            $repositoryMock,
            $entityManagerMock,
            $this->createMock(SearchRankingToSearchRankingClientInterface::class),
            $this->createMock(StatisticsCalculatorInterface::class),
        );

        // Act
        $result = $calibrator->runNextCalibration();

        // Assert
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testSkipsEveryUploadedCalibrationExceptTheNewestWithoutCallingTheSearchClientForThem(): void
    {
        // Arrange
        $newest = $this->createCalibrationTransfer(3, []);
        $older = $this->createCalibrationTransfer(2, []);
        $oldest = $this->createCalibrationTransfer(1, []);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getUploadedCalibrations')->willReturn([$newest, $older, $oldest]);
        $repositoryMock->method('findCalibrationWithSearchTerms')->with(3)->willReturn($newest);

        $skippedIds = [];
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('updateCalibrationStatus')
            ->willReturnCallback(function (int $id, string $status) use (&$skippedIds): void {
                if ($status !== SearchRankingConfig::CALIBRATION_STATUS_SKIPPED) {
                    return;
                }

                $skippedIds[] = $id;
            });
        $entityManagerMock->expects($this->once())->method('markCalibrationFailed');

        $searchRankingClientMock = $this->createMock(SearchRankingToSearchRankingClientInterface::class);
        $searchRankingClientMock->expects($this->never())->method('getCalibrationScores');

        $calibrator = new ScoreCalibrator(
            $repositoryMock,
            $entityManagerMock,
            $searchRankingClientMock,
            $this->createMock(StatisticsCalculatorInterface::class),
        );

        // Act
        $calibrator->runNextCalibration();

        // Assert
        $this->assertSame([2, 1], $skippedIds);
    }

    /**
     * A single search term's Elasticsearch call throwing must not abort the run — it is treated as 0
     * products found for that term, and every other term is still queried.
     *
     * @return void
     */
    public function testTreatsAFailingSearchTermAsZeroScoresWithoutAbortingTheRun(): void
    {
        // Arrange
        $searchTerms = [
            $this->createSearchTermTransfer(10, 'broken'),
            $this->createSearchTermTransfer(11, 'chair'),
        ];
        $calibrationTransfer = $this->createCalibrationTransfer(1, $searchTerms);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getUploadedCalibrations')->willReturn([$calibrationTransfer]);
        $repositoryMock->method('findCalibrationWithSearchTerms')->willReturn($calibrationTransfer);

        $searchRankingClientMock = $this->createMock(SearchRankingToSearchRankingClientInterface::class);
        $searchRankingClientMock->method('getCalibrationScores')
            ->willReturnCallback(function (string $searchTerm) {
                if ($searchTerm === 'broken') {
                    throw new RuntimeException('Elasticsearch is unreachable.');
                }

                return [12.5, 13.5];
            });

        $capturedProductsFoundByTermId = [];
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('saveCalibrationSearchTermResult')
            ->willReturnCallback(function (int $idSearchTerm, int $productsFound) use (&$capturedProductsFoundByTermId): void {
                $capturedProductsFoundByTermId[$idSearchTerm] = $productsFound;
            });

        $statisticsCalculatorMock = $this->createMock(StatisticsCalculatorInterface::class);
        $statisticsCalculatorMock->expects($this->once())
            ->method('calculate')
            ->with([12.5, 13.5])
            ->willReturn(new SearchRankingCalibrationTransfer());

        $calibrator = new ScoreCalibrator($repositoryMock, $entityManagerMock, $searchRankingClientMock, $statisticsCalculatorMock);

        // Act
        $calibrator->runNextCalibration();

        // Assert
        $this->assertSame(0, $capturedProductsFoundByTermId[10]);
        $this->assertSame(2, $capturedProductsFoundByTermId[11]);
    }

    /**
     * @return void
     */
    public function testMarksTheCalibrationFailedWhenNoSearchTermProducedAnyScore(): void
    {
        // Arrange
        $searchTerms = [$this->createSearchTermTransfer(10, 'nomatch')];
        $calibrationTransfer = $this->createCalibrationTransfer(1, $searchTerms);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getUploadedCalibrations')->willReturn([$calibrationTransfer]);
        $repositoryMock->method('findCalibrationWithSearchTerms')->willReturn($calibrationTransfer);

        $searchRankingClientMock = $this->createMock(SearchRankingToSearchRankingClientInterface::class);
        $searchRankingClientMock->method('getCalibrationScores')->willReturn([]);

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('markCalibrationFailed')->with(1, $this->isType('string'));
        $entityManagerMock->expects($this->never())->method('saveCalibrationStatistics');

        $calibrator = new ScoreCalibrator(
            $repositoryMock,
            $entityManagerMock,
            $searchRankingClientMock,
            $this->createMock(StatisticsCalculatorInterface::class),
        );

        // Act
        $calibrator->runNextCalibration();
    }

    /**
     * @param int $idSearchRankingCalibration
     * @param array<\Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer> $searchTermTransfers
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer
     */
    protected function createCalibrationTransfer(int $idSearchRankingCalibration, array $searchTermTransfers): SearchRankingCalibrationTransfer
    {
        $calibrationTransfer = (new SearchRankingCalibrationTransfer())
            ->setIdSearchRankingCalibration($idSearchRankingCalibration)
            ->setRelevantProductCount(6)
            ->setStoreName('DE')
            ->setLocaleName('en_US')
            ->setStatus(SearchRankingConfig::CALIBRATION_STATUS_UPLOADED);

        foreach ($searchTermTransfers as $searchTermTransfer) {
            $calibrationTransfer->addSearchTerm($searchTermTransfer);
        }

        return $calibrationTransfer;
    }

    /**
     * @param int $idSearchRankingCalibrationSearchTerm
     * @param string $searchTerm
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer
     */
    protected function createSearchTermTransfer(int $idSearchRankingCalibrationSearchTerm, string $searchTerm): SearchRankingCalibrationSearchTermTransfer
    {
        return (new SearchRankingCalibrationSearchTermTransfer())
            ->setIdSearchRankingCalibrationSearchTerm($idSearchRankingCalibrationSearchTerm)
            ->setSearchTerm($searchTerm);
    }
}
