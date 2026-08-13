<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Configuration;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Configuration\ConfigurationReader;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Configuration
 * @group ConfigurationReaderTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group Portable
 */
class ConfigurationReaderTest extends Unit
{
    public function testAssemblesEverySettingTheRankingFormulaUsesIntoOneTransfer(): void
    {
        // Arrange
        $configurationReader = $this->createConfigurationReader(
            $this->createRepositoryMock(['top_seller' => 0.4, 'pdp_impressions' => 0.1]),
        );

        // Act
        $configurationTransfer = $configurationReader->getConfiguration('DE', 'de_DE');

        // Assert
        $this->assertSame(['top_seller' => 0.4, 'pdp_impressions' => 0.1], $configurationTransfer->getMetricWeights());
        $this->assertSame(0.6, $configurationTransfer->getRelevanceWeight());
        $this->assertSame(12.0, $configurationTransfer->getRelevanceSaturationPoint());
        $this->assertSame(0.7, $configurationTransfer->getSpecificityBlendWeight());
        $this->assertSame(3.0, $configurationTransfer->getSpecificitySaturationPoint());
        $this->assertSame(2.0, $configurationTransfer->getSpecificityCurveExponent());
        $this->assertSame(1.5, $configurationTransfer->getSpecificityWeightExponent());
        $this->assertSame(0.5, $configurationTransfer->getSpecificityWeightShiftMagnitude());
        $this->assertSame('random', $configurationTransfer->getRandomMetricName());
    }

    /**
     * Every setting is (store, locale)-scoped on this package's own side, so the scope the caller asked
     * for has to reach both the settings and the metric-weight lookup — a reader that silently read one
     * scope's settings alongside another's weights would produce a configuration that never existed.
     */
    public function testThreadsTheRequestedScopeThroughToBothTheSettingsAndTheMetricWeights(): void
    {
        // Arrange
        $capturedScopes = [];

        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getRelevanceWeight')
            ->willReturnCallback(function (string $storeName, string $localeName) use (&$capturedScopes): float {
                $capturedScopes['relevanceWeight'] = [$storeName, $localeName];

                return 0.6;
            });

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getActiveMetricCollection')
            ->willReturnCallback(function (string $storeName, string $localeName) use (&$capturedScopes): SearchRankingMetricCollectionTransfer {
                $capturedScopes['activeMetricCollection'] = [$storeName, $localeName];

                return new SearchRankingMetricCollectionTransfer();
            });
        $repositoryMock->method('attachWeights')
            ->willReturnCallback(function (
                SearchRankingMetricCollectionTransfer $collectionTransfer,
                string $storeName,
                string $localeName,
            ) use (&$capturedScopes): SearchRankingMetricCollectionTransfer {
                $capturedScopes['attachWeights'] = [$storeName, $localeName];

                return $collectionTransfer;
            });

        $configurationReader = $this->createConfigurationReader($repositoryMock, $settingManagerMock);

        // Act
        $configurationReader->getConfiguration('AT', 'fr_FR');

        // Assert
        $this->assertEqualsCanonicalizing([
            'relevanceWeight' => ['AT', 'fr_FR'],
            'activeMetricCollection' => ['AT', 'fr_FR'],
            'attachWeights' => ['AT', 'fr_FR'],
        ], $capturedScopes);
    }

    /**
     * The published storefront document normalizes weights to sum to 1, but that is the publisher's own
     * derived view — this read must hand back the raw numbers a curator entered, since a consumer tuning
     * weights (search-ranking-optimizer) has to start from the live values, not a rescaled copy.
     */
    public function testReturnsRawUnnormalizedMetricWeights(): void
    {
        // Arrange
        $configurationReader = $this->createConfigurationReader(
            $this->createRepositoryMock(['top_seller' => 2.0, 'pdp_impressions' => 2.0]),
        );

        // Act
        $configurationTransfer = $configurationReader->getConfiguration('DE', 'de_DE');

        // Assert
        $this->assertSame(['top_seller' => 2.0, 'pdp_impressions' => 2.0], $configurationTransfer->getMetricWeights());
    }

    /**
     * A scope with nothing active at all is a legitimate state (a store never set up yet), not an error —
     * it must produce an empty weight map alongside the still-present scalar defaults.
     */
    public function testReturnsAnEmptyWeightMapWhenNoMetricIsActiveInThisScope(): void
    {
        // Arrange
        $configurationReader = $this->createConfigurationReader($this->createRepositoryMock([]));

        // Act
        $configurationTransfer = $configurationReader->getConfiguration('DE', 'de_DE');

        // Assert
        $this->assertSame([], $configurationTransfer->getMetricWeights());
        $this->assertSame(0.6, $configurationTransfer->getRelevanceWeight());
    }

    /**
     * @param array<string, float> $metricWeights
     */
    protected function createRepositoryMock(array $metricWeights): SearchRankingRepositoryInterface
    {
        $collectionTransfer = new SearchRankingMetricCollectionTransfer();

        foreach ($metricWeights as $name => $weight) {
            $collectionTransfer->addMetric(
                (new SearchRankingMetricTransfer())
                    ->setIdSearchRankingMetric(1)
                    ->setName($name)
                    ->setWeight($weight)
                    ->setFormula('x')
                    ->setIsActive(true),
            );
        }

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getActiveMetricCollection')->willReturn($collectionTransfer);
        $repositoryMock->method('attachWeights')->willReturn($collectionTransfer);

        return $repositoryMock;
    }

    protected function createSettingManagerMock(): SettingManagerInterface
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getRelevanceWeight')->willReturn(0.6);
        $settingManagerMock->method('getRelevanceSaturationPoint')->willReturn(12.0);
        $settingManagerMock->method('getSpecificityBlendWeight')->willReturn(0.7);
        $settingManagerMock->method('getSpecificitySaturationPoint')->willReturn(3.0);
        $settingManagerMock->method('getSpecificityCurveExponent')->willReturn(2.0);
        $settingManagerMock->method('getSpecificityWeightExponent')->willReturn(1.5);
        $settingManagerMock->method('getSpecificityWeightShiftMagnitude')->willReturn(0.5);

        return $settingManagerMock;
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface|null $settingManager
     */
    protected function createConfigurationReader(
        SearchRankingRepositoryInterface $repository,
        ?SettingManagerInterface $settingManager = null,
    ): ConfigurationReader {
        $configMock = $this->createMock(SearchRankingConfig::class);
        $configMock->method('getRandomMetricName')->willReturn('random');

        return new ConfigurationReader(
            $repository,
            $settingManager ?? $this->createSettingManagerMock(),
            $configMock,
        );
    }
}
