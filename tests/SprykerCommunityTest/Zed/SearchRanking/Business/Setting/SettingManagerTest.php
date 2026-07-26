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
        $relevanceWeight = $settingManager->getRelevanceWeight();

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
        $relevanceWeight = $settingManager->getRelevanceWeight();

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
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT, '0.42');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveRelevanceWeight(0.42);
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
        $relevanceSaturationPoint = $settingManager->getRelevanceSaturationPoint();

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
        $relevanceSaturationPoint = $settingManager->getRelevanceSaturationPoint();

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
            ->with(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT, '15');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveRelevanceSaturationPoint(15.0);
    }

    /**
     * @return void
     */
    public function testReturnsTheSavedEntropyProbeResultSizeWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_PROBE_RESULT_SIZE)
            ->willReturn('20');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $entropyProbeResultSize = $settingManager->getEntropyProbeResultSize();

        // Assert
        $this->assertSame(20, $entropyProbeResultSize);
    }

    /**
     * @return void
     */
    public function testFallsBackToTheConfigDefaultWhenNoEntropyProbeResultSizeIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_PROBE_RESULT_SIZE)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $entropyProbeResultSize = $settingManager->getEntropyProbeResultSize();

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultEntropyProbeResultSize(), $entropyProbeResultSize);
    }

    /**
     * @return void
     */
    public function testSavesTheEntropyProbeResultSizeAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_PROBE_RESULT_SIZE, '20');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveEntropyProbeResultSize(20);
    }

    /**
     * @return void
     */
    public function testReturnsTheSavedEntropyWeightExponentWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_WEIGHT_EXPONENT)
            ->willReturn('1.5');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $entropyWeightExponent = $settingManager->getEntropyWeightExponent();

        // Assert
        $this->assertSame(1.5, $entropyWeightExponent);
    }

    /**
     * @return void
     */
    public function testFallsBackToTheConfigDefaultWhenNoEntropyWeightExponentIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_WEIGHT_EXPONENT)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $entropyWeightExponent = $settingManager->getEntropyWeightExponent();

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultEntropyWeightExponent(), $entropyWeightExponent);
    }

    /**
     * @return void
     */
    public function testSavesTheEntropyWeightExponentAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_WEIGHT_EXPONENT, '1.5');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveEntropyWeightExponent(1.5);
    }

    /**
     * @return void
     */
    public function testReturnsTheSavedEntropyWeightShiftMagnitudeWhenOneExists(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_WEIGHT_SHIFT_MAGNITUDE)
            ->willReturn('0.3');

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $entropyWeightShiftMagnitude = $settingManager->getEntropyWeightShiftMagnitude();

        // Assert
        $this->assertSame(0.3, $entropyWeightShiftMagnitude);
    }

    /**
     * @return void
     */
    public function testFallsBackToTheConfigDefaultWhenNoEntropyWeightShiftMagnitudeIsSaved(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('findSettingValue')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_WEIGHT_SHIFT_MAGNITUDE)
            ->willReturn(null);

        $settingManager = $this->createSettingManager($repositoryMock);

        // Act
        $entropyWeightShiftMagnitude = $settingManager->getEntropyWeightShiftMagnitude();

        // Assert
        $this->assertSame((new SearchRankingConfig())->getDefaultEntropyWeightShiftMagnitude(), $entropyWeightShiftMagnitude);
    }

    /**
     * @return void
     */
    public function testSavesTheEntropyWeightShiftMagnitudeAsAStringUnderItsSettingKey(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('saveSetting')
            ->with(SharedSearchRankingConfig::SETTING_KEY_ENTROPY_WEIGHT_SHIFT_MAGNITUDE, '0.3');

        $settingManager = $this->createSettingManager($repositoryMock, $entityManagerMock);

        // Act
        $settingManager->saveEntropyWeightShiftMagnitude(0.3);
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
