<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\ScopeCopy;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingScopeCopyPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigPreviewTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\FullScopeCopier;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group ScopeCopy
 * @group FullScopeCopierTest
 * Add your own group annotations below this line
 */
class FullScopeCopierTest extends Unit
{
    public function testFailsWhenSourceAndTargetScopeAreTheSame(): void
    {
        // Arrange
        $scopeConfigCopierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $scopeConfigCopierMock->expects($this->never())->method('copyScopeConfiguration');
        $storeConfigCopierMock = $this->createMock(StoreConfigCopierInterface::class);
        $storeConfigCopierMock->expects($this->never())->method('copyStoreConfiguration');

        // Act
        $resultTransfer = (new FullScopeCopier($scopeConfigCopierMock, $storeConfigCopierMock))->copyFullScopeConfiguration(
            'DE',
            'de_DE',
            'DE',
            'de_DE',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertFalse($resultTransfer->getIsSuccess());
        $this->assertNotNull($resultTransfer->getErrorMessage());
    }

    /**
     * Blocked BEFORE either half writes anything — neither copyScopeConfiguration() nor
     * copyStoreConfiguration() may run, otherwise a blocked combined copy could still tear a half-write
     * into the target.
     */
    public function testIsBlockedByExistingDataBeforeEitherHalfWritesWhenOverwriteNotConfirmed(): void
    {
        // Arrange
        $scopeConfigCopierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $scopeConfigCopierMock->method('hasBlockingExistingData')->with('DE', 'de_DE', 'AT', 'de_AT')->willReturn(true);
        $scopeConfigCopierMock->expects($this->never())->method('copyScopeConfiguration');
        $storeConfigCopierMock = $this->createMock(StoreConfigCopierInterface::class);
        $storeConfigCopierMock->expects($this->never())->method('hasStoreConfiguration');
        $storeConfigCopierMock->expects($this->never())->method('copyStoreConfiguration');

        // Act
        $resultTransfer = (new FullScopeCopier($scopeConfigCopierMock, $storeConfigCopierMock))->copyFullScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsBlockedByExistingData());
    }

    /**
     * Both halves run, both forced confirmOverwrite=true (the real collision check already ran via
     * hasFullScopeConfiguration()'s pre-flight — re-deriving it a second time inside each sub-copier's own
     * guard would risk the two disagreeing), and both halves' counts land in the combined result.
     */
    public function testCopiesBothHalvesAndAggregatesTheirCounts(): void
    {
        // Arrange
        $scopeConfigCopierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $scopeConfigCopierMock->method('hasBlockingExistingData')->willReturn(false);
        $scopeConfigCopierMock->expects($this->once())
            ->method('copyScopeConfiguration')
            ->with('DE', 'de_DE', 'AT', 'de_AT', ScopeConfigCopierInterface::MODE_MIRROR, true, SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY)
            ->willReturn(
                (new SearchRankingScopeCopyResultTransfer())
                    ->setIsSuccess(true)
                    ->setMetricWeightCopiedCount(3)
                    ->setSettingCopiedCount(2)
                    ->setSkippedCount(0),
            );

        $storeConfigCopierMock = $this->createMock(StoreConfigCopierInterface::class);
        $storeConfigCopierMock->method('hasStoreConfiguration')->willReturn(false);
        $storeConfigCopierMock->expects($this->once())
            ->method('copyStoreConfiguration')
            ->with('DE', 'de_DE', 'AT', 'de_AT', ScopeConfigCopierInterface::MODE_MIRROR, true, SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY)
            ->willReturn(
                (new SearchRankingStoreConfigCopyResultTransfer())
                    ->setIsSuccess(true)
                    ->setCopiedCount(4)
                    ->setSkippedCount(1),
            );

        // Act
        $resultTransfer = (new FullScopeCopier($scopeConfigCopierMock, $storeConfigCopierMock))->copyFullScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsSuccess());
        $this->assertSame(3, $resultTransfer->getMetricWeightCopiedCount());
        $this->assertSame(2, $resultTransfer->getSettingCopiedCount());
        $this->assertSame(4, $resultTransfer->getStoreConfigCopiedCount());
        $this->assertSame(1, $resultTransfer->getStoreConfigSkippedCount());
    }

    /**
     * When source and target are the SAME store (only the locale differs), the store-config half is
     * skipped entirely rather than erroring the whole combined copy — StoreConfigCopierInterface's own
     * guard rejects a same-store call outright, but the weight/setting half is still perfectly valid.
     */
    public function testSkipsTheStoreConfigHalfWhenSourceAndTargetAreTheSameStore(): void
    {
        // Arrange
        $scopeConfigCopierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $scopeConfigCopierMock->method('hasBlockingExistingData')->willReturn(false);
        $scopeConfigCopierMock->expects($this->once())
            ->method('copyScopeConfiguration')
            ->willReturn((new SearchRankingScopeCopyResultTransfer())->setIsSuccess(true)->setMetricWeightCopiedCount(1)->setSettingCopiedCount(0)->setSkippedCount(0));

        $storeConfigCopierMock = $this->createMock(StoreConfigCopierInterface::class);
        $storeConfigCopierMock->expects($this->never())->method('hasStoreConfiguration');
        $storeConfigCopierMock->expects($this->never())->method('copyStoreConfiguration');

        // Act
        $resultTransfer = (new FullScopeCopier($scopeConfigCopierMock, $storeConfigCopierMock))->copyFullScopeConfiguration(
            'DE',
            'de_DE',
            'DE',
            'fr_DE',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsSuccess());
        $this->assertSame(0, $resultTransfer->getStoreConfigCopiedCount());
        $this->assertSame(0, $resultTransfer->getStoreConfigSkippedCount());
    }

    public function testHasFullScopeConfigurationSkipsTheStoreConfigCheckWhenSourceAndTargetAreTheSameStore(): void
    {
        // Arrange
        $scopeConfigCopierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $scopeConfigCopierMock->method('hasBlockingExistingData')->willReturn(false);

        $storeConfigCopierMock = $this->createMock(StoreConfigCopierInterface::class);
        $storeConfigCopierMock->expects($this->never())->method('hasStoreConfiguration');

        // Act
        $result = (new FullScopeCopier($scopeConfigCopierMock, $storeConfigCopierMock))
            ->hasFullScopeConfiguration('DE', 'de_DE', 'DE', 'fr_DE');

        // Assert
        $this->assertFalse($result);
    }

    public function testHasFullScopeConfigurationIsTrueWhenTheTargetStoreAlreadyHasStoreConfig(): void
    {
        // Arrange
        $scopeConfigCopierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $scopeConfigCopierMock->method('hasBlockingExistingData')->willReturn(false);

        $storeConfigCopierMock = $this->createMock(StoreConfigCopierInterface::class);
        $storeConfigCopierMock->method('hasStoreConfiguration')->with('AT')->willReturn(true);

        // Act
        $result = (new FullScopeCopier($scopeConfigCopierMock, $storeConfigCopierMock))
            ->hasFullScopeConfiguration('DE', 'de_DE', 'AT', 'de_AT');

        // Assert
        $this->assertTrue($result);
    }

    public function testPreviewCombinesBothHalvesPreviews(): void
    {
        // Arrange
        $scopeConfigCopierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $scopeConfigCopierMock->method('previewScopeConfiguration')->with('DE', 'de_DE')->willReturn(
            (new SearchRankingScopeCopyPreviewTransfer())
                ->setMetricWeights(['top_seller' => 0.8])
                ->setSettings(['Relevance weight' => 0.6]),
        );

        $storeConfigCopierMock = $this->createMock(StoreConfigCopierInterface::class);
        $storeConfigCopierMock->method('previewStoreConfiguration')->with('DE', 'de_DE')->willReturn(
            (new SearchRankingStoreConfigPreviewTransfer())
                ->setMetrics(['top_seller' => ['formula' => 'x / max', 'isActive' => true]]),
        );

        // Act
        $previewTransfer = (new FullScopeCopier($scopeConfigCopierMock, $storeConfigCopierMock))
            ->previewFullScopeConfiguration('DE', 'de_DE');

        // Assert
        $this->assertSame(['top_seller' => 0.8], $previewTransfer->getMetricWeights());
        $this->assertSame(['Relevance weight' => 0.6], $previewTransfer->getSettings());
        $this->assertSame(['top_seller' => ['formula' => 'x / max', 'isActive' => true]], $previewTransfer->getStoreConfigMetrics());
    }
}
