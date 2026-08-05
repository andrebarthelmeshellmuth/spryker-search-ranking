<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Setting;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Shared\SearchRanking\SearchRankingEvents;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManager;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface;
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
 * @group Setting
 * @group SettingManagerTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class SettingManagerTest extends Unit
{
    public function testReturnsTheSavedRelevanceWeightWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT)
            ->willReturn('0.75');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $relevanceWeight = $settingManager->getRelevanceWeight('DE', 'de_DE');

        // Assert
        $this->assertSame(0.75, $relevanceWeight);
    }

    public function testFallsBackToTheConfigDefaultWhenNoRelevanceWeightIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $relevanceWeight = $settingManager->getRelevanceWeight('DE', 'de_DE');

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultRelevanceWeight(), $relevanceWeight);
    }

    public function testSavesTheRelevanceWeightAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('0.3');

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '0.42');
        $entityManagerMock->expects($this->once())
            ->method('recordSettingHistory')
            ->with($this->equalTo(
                (new SearchRankingSettingHistoryTransfer())
                    ->setSettingKey(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT)
                    ->setStoreName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
                    ->setLocaleName(SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
                    ->setSettingValue('0.42'),
            ));

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveRelevanceWeight('DE', 'de_DE', 0.42);
    }

    public function testDoesNotRecordHistoryWhenTheSavedRelevanceWeightIsUnchanged(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('0.42');

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('recordSettingHistory');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveRelevanceWeight('DE', 'de_DE', 0.42);
    }

    public function testReturnsTheSavedRelevanceSaturationPointWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT)
            ->willReturn('20.5');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $relevanceSaturationPoint = $settingManager->getRelevanceSaturationPoint('DE', 'de_DE');

        // Assert
        $this->assertSame(20.5, $relevanceSaturationPoint);
    }

    public function testFallsBackToTheConfigDefaultWhenNoRelevanceSaturationPointIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $relevanceSaturationPoint = $settingManager->getRelevanceSaturationPoint('DE', 'de_DE');

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultRelevanceSaturationPoint(), $relevanceSaturationPoint);
    }

    public function testSavesTheRelevanceSaturationPointAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('20.5');

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '15');
        $entityManagerMock->expects($this->once())
            ->method('recordSettingHistory')
            ->with($this->equalTo(
                (new SearchRankingSettingHistoryTransfer())
                    ->setSettingKey(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT)
                    ->setStoreName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
                    ->setLocaleName(SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
                    ->setSettingValue('15'),
            ));

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveRelevanceSaturationPoint('DE', 'de_DE', 15.0);
    }

    public function testReturnsTheSavedSpecificityBlendWeightWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT)
            ->willReturn('0.8');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $specificityBlendWeight = $settingManager->getSpecificityBlendWeight('DE', 'de_DE');

        // Assert
        $this->assertSame(0.8, $specificityBlendWeight);
    }

    public function testFallsBackToTheConfigDefaultWhenNoSpecificityBlendWeightIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $specificityBlendWeight = $settingManager->getSpecificityBlendWeight('DE', 'de_DE');

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultSpecificityBlendWeight(), $specificityBlendWeight);
    }

    public function testSavesTheSpecificityBlendWeightAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('0.5');

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '0.8');
        $entityManagerMock->expects($this->once())
            ->method('recordSettingHistory')
            ->with($this->equalTo(
                (new SearchRankingSettingHistoryTransfer())
                    ->setSettingKey(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT)
                    ->setStoreName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
                    ->setLocaleName(SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
                    ->setSettingValue('0.8'),
            ));

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveSpecificityBlendWeight('DE', 'de_DE', 0.8);
    }

    public function testReturnsTheSavedSpecificitySaturationPointWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT)
            ->willReturn('4.0');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $specificitySaturationPoint = $settingManager->getSpecificitySaturationPoint('DE', 'de_DE');

        // Assert
        $this->assertSame(4.0, $specificitySaturationPoint);
    }

    public function testFallsBackToTheConfigDefaultWhenNoSpecificitySaturationPointIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $specificitySaturationPoint = $settingManager->getSpecificitySaturationPoint('DE', 'de_DE');

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultSpecificitySaturationPoint(), $specificitySaturationPoint);
    }

    public function testSavesTheSpecificitySaturationPointAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('3');

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '4');
        $entityManagerMock->expects($this->once())
            ->method('recordSettingHistory')
            ->with($this->equalTo(
                (new SearchRankingSettingHistoryTransfer())
                    ->setSettingKey(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT)
                    ->setStoreName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
                    ->setLocaleName(SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
                    ->setSettingValue('4'),
            ));

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveSpecificitySaturationPoint('DE', 'de_DE', 4.0);
    }

    public function testReturnsTheSavedSpecificityWeightExponentWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT)
            ->willReturn('1.5');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $specificityWeightExponent = $settingManager->getSpecificityWeightExponent('DE', 'de_DE');

        // Assert
        $this->assertSame(1.5, $specificityWeightExponent);
    }

    public function testFallsBackToTheConfigDefaultWhenNoSpecificityWeightExponentIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $specificityWeightExponent = $settingManager->getSpecificityWeightExponent('DE', 'de_DE');

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultSpecificityWeightExponent(), $specificityWeightExponent);
    }

    public function testSavesTheSpecificityWeightExponentAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('1.0');

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '1.5');
        $entityManagerMock->expects($this->once())
            ->method('recordSettingHistory')
            ->with($this->equalTo(
                (new SearchRankingSettingHistoryTransfer())
                    ->setSettingKey(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT)
                    ->setStoreName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
                    ->setLocaleName(SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
                    ->setSettingValue('1.5'),
            ));

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveSpecificityWeightExponent('DE', 'de_DE', 1.5);
    }

    public function testReturnsTheSavedSpecificityWeightShiftMagnitudeWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE)
            ->willReturn('0.3');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $specificityWeightShiftMagnitude = $settingManager->getSpecificityWeightShiftMagnitude('DE', 'de_DE');

        // Assert
        $this->assertSame(0.3, $specificityWeightShiftMagnitude);
    }

    public function testFallsBackToTheConfigDefaultWhenNoSpecificityWeightShiftMagnitudeIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $specificityWeightShiftMagnitude = $settingManager->getSpecificityWeightShiftMagnitude('DE', 'de_DE');

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultSpecificityWeightShiftMagnitude(), $specificityWeightShiftMagnitude);
    }

    public function testSavesTheSpecificityWeightShiftMagnitudeAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('0.2');

        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '0.3');
        $entityManagerMock->expects($this->once())
            ->method('recordSettingHistory')
            ->with($this->equalTo(
                (new SearchRankingSettingHistoryTransfer())
                    ->setSettingKey(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE)
                    ->setStoreName(SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME)
                    ->setLocaleName(SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)
                    ->setSettingValue('0.3'),
            ));

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveSpecificityWeightShiftMagnitude('DE', 'de_DE', 0.3);
    }

    public function testTriggersRankingConfigurationChangeEventWhenASettingIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('0.3');

        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->expects($this->once())->method('trigger')->with(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE);

        $settingManager = $this->createSettingManager($repositoryMock, null, $eventFacadeMock);

        // Act
        $settingManager->saveRelevanceWeight('DE', 'de_DE', 0.42);
    }

    /**
     * The event fires even when resubmitting the same value — republishing an unchanged value is
     * harmless, and gating the event on "did it change" would reintroduce exactly the kind of easy-to-get-
     * wrong conditional this centralization is meant to eliminate.
     */
    public function testTriggersRankingConfigurationChangeEventEvenWhenTheValueIsUnchanged(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')->willReturn('0.42');

        $eventFacadeMock = $this->createMock(SearchRankingToEventFacadeInterface::class);
        $eventFacadeMock->expects($this->once())->method('trigger')->with(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE);

        $settingManager = $this->createSettingManager($repositoryMock, null, $eventFacadeMock);

        // Act
        $settingManager->saveRelevanceWeight('DE', 'de_DE', 0.42);
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface|null $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface|null $eventFacade
     */
    protected function createSettingManager(
        SearchRankingRepositoryInterface $repository,
        ?SearchRankingEntityManagerInterface $entityManager = null,
        ?SearchRankingToEventFacadeInterface $eventFacade = null,
    ): SettingManager {
        return new SettingManager(
            $repository,
            $entityManager ?? $this->createMock(SearchRankingEntityManagerInterface::class),
            new SearchRankingConfig(),
            $eventFacade ?? $this->createMock(SearchRankingToEventFacadeInterface::class),
        );
    }
}
