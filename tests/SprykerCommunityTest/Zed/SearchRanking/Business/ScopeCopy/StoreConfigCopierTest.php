<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\ScopeCopy;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopier;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group ScopeCopy
 * @group StoreConfigCopierTest
 * Add your own group annotations below this line
 */
class StoreConfigCopierTest extends Unit
{
    public function testFailsWhenSourceAndTargetStoreAreTheSame(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->never())->method('saveMetric');

        // Act
        $resultTransfer = (new StoreConfigCopier($repositoryMock, $metricWriterMock))->copyStoreConfiguration(
            'DE',
            'de_DE',
            'DE',
            'de_DE',
            StoreConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertFalse($resultTransfer->getIsSuccess());
        $this->assertNotNull($resultTransfer->getErrorMessage());
    }

    public function testIsBlockedByExistingDataWhenTargetAlreadyHasConfigurationAndOverwriteNotConfirmed(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasStoreConfiguration')->with('AT')->willReturn(true);
        $repositoryMock->expects($this->never())->method('getMetricCollection');

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->never())->method('saveMetric');

        // Act
        $resultTransfer = (new StoreConfigCopier($repositoryMock, $metricWriterMock))->copyStoreConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            StoreConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsBlockedByExistingData());
    }

    public function testOverwriteConfirmedBypassesTheBlockedByExistingDataGuard(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasStoreConfiguration')->willReturn(true);
        $repositoryMock->method('getMetricCollection')->willReturn(new SearchRankingMetricCollectionTransfer());

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);

        // Act
        $resultTransfer = (new StoreConfigCopier($repositoryMock, $metricWriterMock))->copyStoreConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            StoreConfigCopierInterface::MODE_MIRROR,
            true,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertNotTrue($resultTransfer->getIsBlockedByExistingData());
        $this->assertTrue($resultTransfer->getIsSuccess());
    }

    /**
     * Mirror copies every metric explicitly configured in the source (real formula, not null) — including
     * creating a target row for one the target never had at all — and silently skips one the SOURCE itself
     * never configured (formula null, the safe-absence signal), without counting it anywhere.
     */
    public function testMirrorModeCopiesEveryExplicitlyConfiguredSourceMetricRegardlessOfTargetState(): void
    {
        // Arrange
        $configuredMetricA = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(1)->setName('top_seller')->setFormula('x / max')->setIsActive(true);
        $configuredMetricB = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(2)->setName('pdp_impressions')->setFormula('x / avg')->setIsActive(false);
        $neverConfiguredMetric = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(3)->setName('random')->setFormula(null)->setIsActive(false);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasStoreConfiguration')->willReturn(false);
        $repositoryMock->method('getMetricCollection')->with('DE', 'de_DE')->willReturn(
            (new SearchRankingMetricCollectionTransfer())
                ->addMetric($configuredMetricA)
                ->addMetric($configuredMetricB)
                ->addMetric($neverConfiguredMetric),
        );

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->exactly(2))
            ->method('saveMetric')
            ->with($this->callback(fn (SearchRankingMetricTransfer $transfer): bool => in_array($transfer->getIdSearchRankingMetric(), [1, 2], true)
                && $transfer->getChangeSource() === SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY), 'AT', 'de_AT')
            ->willReturnArgument(0);

        // Act
        $resultTransfer = (new StoreConfigCopier($repositoryMock, $metricWriterMock))->copyStoreConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            StoreConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsSuccess());
        $this->assertSame(2, $resultTransfer->getCopiedCount());
        $this->assertSame(0, $resultTransfer->getSkippedCount());
    }

    /**
     * The real regression this covers: MODE_COPY_ONLY_OVERLAP must leave a metric alone that the TARGET
     * has never independently adopted, rather than creating a new row for it the way MODE_MIRROR would —
     * counted in skippedCount, not an error.
     */
    public function testCopyOnlyOverlapModeSkipsAMetricTheTargetHasNeverAdopted(): void
    {
        // Arrange
        $notYetAdoptedByTarget = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(1)->setName('top_seller')->setFormula('x / max')->setIsActive(true);
        $alreadyAdoptedByTarget = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(2)->setName('pdp_impressions')->setFormula('x / avg')->setIsActive(true);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasStoreConfiguration')->willReturn(false);
        $repositoryMock->method('getMetricCollection')->willReturn(
            (new SearchRankingMetricCollectionTransfer())
                ->addMetric($notYetAdoptedByTarget)
                ->addMetric($alreadyAdoptedByTarget),
        );
        $repositoryMock->method('findMetricStoreConfig')->willReturnMap([
            [1, 'AT', null],
            [2, 'AT', new SearchRankingMetricStoreConfigTransfer()],
        ]);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->once())
            ->method('saveMetric')
            ->with($this->callback(fn (SearchRankingMetricTransfer $transfer): bool => $transfer->getIdSearchRankingMetric() === 2), 'AT', 'de_AT')
            ->willReturnArgument(0);

        // Act
        $resultTransfer = (new StoreConfigCopier($repositoryMock, $metricWriterMock))->copyStoreConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            StoreConfigCopierInterface::MODE_COPY_ONLY_OVERLAP,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertSame(1, $resultTransfer->getCopiedCount());
        $this->assertSame(1, $resultTransfer->getSkippedCount());
    }

    public function testHasStoreConfigurationDelegatesToTheRepository(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->expects($this->once())->method('hasStoreConfiguration')->with('AT')->willReturn(true);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);

        // Act
        $result = (new StoreConfigCopier($repositoryMock, $metricWriterMock))->hasStoreConfiguration('AT');

        // Assert
        $this->assertTrue($result);
    }
}
