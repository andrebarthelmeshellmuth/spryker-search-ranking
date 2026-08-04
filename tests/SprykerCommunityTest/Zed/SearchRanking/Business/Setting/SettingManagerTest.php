<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Setting;

use Codeception\Test\Unit;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManager;
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
    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testSavesTheRelevanceWeightAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '0.42');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveRelevanceWeight('DE', 'de_DE', 0.42);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testSavesTheRelevanceSaturationPointAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '15');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveRelevanceSaturationPoint('DE', 'de_DE', 15.0);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testSavesTheSpecificityBlendWeightAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '0.8');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveSpecificityBlendWeight('DE', 'de_DE', 0.8);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testSavesTheSpecificitySaturationPointAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '4');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveSpecificitySaturationPoint('DE', 'de_DE', 4.0);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testSavesTheSpecificityWeightExponentAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '1.5');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveSpecificityWeightExponent('DE', 'de_DE', 1.5);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testSavesTheSpecificityWeightShiftMagnitudeAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME, '0.3');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveSpecificityWeightShiftMagnitude('DE', 'de_DE', 0.3);
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface|null $entityManager
     *
     * @return \SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManager
     */
    protected function createSettingManager(
        SearchRankingRepositoryInterface $repository,
        ?SearchRankingEntityManagerInterface $entityManager = null,
    ): SettingManager {
        return new SettingManager(
            $repository,
            $entityManager ?? $this->createMock(SearchRankingEntityManagerInterface::class),
            new SearchRankingConfig(),
        );
    }
}
