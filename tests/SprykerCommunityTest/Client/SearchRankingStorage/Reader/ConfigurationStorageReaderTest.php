<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRankingStorage\Reader;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SynchronizationDataTransfer;
use ReflectionProperty;
use Spryker\Service\Synchronization\Dependency\Plugin\SynchronizationKeyGeneratorPluginInterface;
use SprykerCommunity\Client\SearchRankingStorage\Dependency\Client\SearchRankingStorageToStorageClientInterface;
use SprykerCommunity\Client\SearchRankingStorage\Dependency\Service\SearchRankingStorageToSynchronizationServiceInterface;
use SprykerCommunity\Client\SearchRankingStorage\Reader\ConfigurationStorageReader;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRankingStorage
 * @group Reader
 * @group ConfigurationStorageReaderTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRankingStorage\SearchRankingStorageClientTester $tester
 * @group Portable
 */
class ConfigurationStorageReaderTest extends Unit
{
    protected function _before(): void
    {
        parent::_before();

        // ConfigurationStorageReader::$rankingConfigurationCache is memoized on the CLASS (static), so a
        // value read by an earlier test in this process would otherwise leak into the next one.
        $this->resetMemoizedCache();
    }

    protected function _after(): void
    {
        $this->resetMemoizedCache();

        parent::_after();
    }

    public function testParsesAllFieldsFromTheStoredDocument(): void
    {
        // Arrange
        $reader = $this->createReader([
            'metric_weights' => ['top_seller' => 0.5, 'pdp_impressions' => 0.3],
            'relevance_weight' => 0.6,
            'relevance_saturation_point' => 12.0,
            'specificity_blend_weight' => 0.8,
            'specificity_saturation_point' => 4.0,
            'specificity_curve_exponent' => 2.5,
            'specificity_weight_exponent' => 1.5,
            'specificity_weight_shift_magnitude' => 0.3,
            'random_metric_name' => 'noise',
        ]);

        // Act
        $configurationTransfer = $reader->findRankingConfiguration('DE', 'de_DE');

        // Assert
        $this->assertSame(['top_seller' => 0.5, 'pdp_impressions' => 0.3], $configurationTransfer->getMetricWeights());
        $this->assertSame(0.6, $configurationTransfer->getRelevanceWeight());
        $this->assertSame(12.0, $configurationTransfer->getRelevanceSaturationPoint());
        $this->assertSame(0.8, $configurationTransfer->getSpecificityBlendWeight());
        $this->assertSame(4.0, $configurationTransfer->getSpecificitySaturationPoint());
        $this->assertSame(2.5, $configurationTransfer->getSpecificityCurveExponent());
        $this->assertSame(1.5, $configurationTransfer->getSpecificityWeightExponent());
        $this->assertSame(0.3, $configurationTransfer->getSpecificityWeightShiftMagnitude());
        $this->assertSame('noise', $configurationTransfer->getRandomMetricName());
    }

    public function testReturnsNullWhenNoDocumentIsStoredUnderTheKey(): void
    {
        // Arrange
        $reader = $this->createReader(null);

        // Act
        $configurationTransfer = $reader->findRankingConfiguration('DE', 'de_DE');

        // Assert
        $this->assertNull($configurationTransfer);
    }

    /**
     * A missing key inside an otherwise-present document must default rather than error — this can
     * legitimately happen right after upgrading the package, before the first republish. Every field
     * must default to the same values `SearchRankingConfig`'s own Zed defaults use, NOT throw a
     * `getXOrFail()`-style exception, since that would break every live catalog search the moment a
     * project flips a new feature on before its first post-upgrade republish.
     */
    public function testDefaultsMissingKeysInsteadOfErroring(): void
    {
        // Arrange
        $reader = $this->createReader([]);

        // Act
        $configurationTransfer = $reader->findRankingConfiguration('DE', 'de_DE');

        // Assert
        $this->assertSame([], $configurationTransfer->getMetricWeights());
        $this->assertSame(0.75, $configurationTransfer->getRelevanceWeight());
        $this->assertSame(12.0, $configurationTransfer->getRelevanceSaturationPoint());
        $this->assertSame(0.7, $configurationTransfer->getSpecificityBlendWeight());
        $this->assertSame(3.0, $configurationTransfer->getSpecificitySaturationPoint());
        $this->assertSame(1.0, $configurationTransfer->getSpecificityCurveExponent());
        $this->assertSame(1.0, $configurationTransfer->getSpecificityWeightExponent());
        $this->assertSame(0.25, $configurationTransfer->getSpecificityWeightShiftMagnitude());
        $this->assertSame('random', $configurationTransfer->getRandomMetricName());
    }

    /**
     * The storage client is only ever hit once per reader instance — the second call must be served from
     * the memoized value.
     */
    public function testMemoizesTheResultAcrossRepeatedCallsOnTheSameInstance(): void
    {
        // Arrange
        $storageClientMock = $this->createMock(SearchRankingStorageToStorageClientInterface::class);
        $storageClientMock->expects($this->once())
            ->method('get')
            ->willReturn(['metric_weights' => [], 'relevance_weight' => 0.6, 'relevance_saturation_point' => 12.0]);

        $reader = new ConfigurationStorageReader($storageClientMock, $this->createSynchronizationServiceMock());

        // Act
        $reader->findRankingConfiguration('DE', 'de_DE');
        $reader->findRankingConfiguration('DE', 'de_DE');
    }

    /**
     * Two different (store, locale) scopes must be memoized independently — a DE lookup must not be
     * served from an AT value cached earlier in the same process, and vice versa.
     */
    public function testMemoizesEachStoreAndLocaleScopeIndependently(): void
    {
        // Arrange
        $storageClientMock = $this->createMock(SearchRankingStorageToStorageClientInterface::class);
        $storageClientMock->expects($this->exactly(2))
            ->method('get')
            ->willReturn(['metric_weights' => [], 'relevance_weight' => 0.6, 'relevance_saturation_point' => 12.0]);

        $reader = new ConfigurationStorageReader($storageClientMock, $this->createSynchronizationServiceMock());

        // Act
        $reader->findRankingConfiguration('DE', 'de_DE');
        $reader->findRankingConfiguration('DE', 'de_DE');
        $reader->findRankingConfiguration('AT', 'de_AT');
        $reader->findRankingConfiguration('AT', 'de_AT');
    }

    /**
     * @param array<string, mixed>|null $storedDocument
     */
    protected function createReader(?array $storedDocument): ConfigurationStorageReader
    {
        $storageClientMock = $this->createMock(SearchRankingStorageToStorageClientInterface::class);
        $storageClientMock->method('get')->willReturn($storedDocument);

        return new ConfigurationStorageReader($storageClientMock, $this->createSynchronizationServiceMock());
    }

    protected function createSynchronizationServiceMock(): SearchRankingStorageToSynchronizationServiceInterface
    {
        $keyGeneratorMock = $this->createMock(SynchronizationKeyGeneratorPluginInterface::class);
        $keyGeneratorMock->method('generateKey')->with($this->isInstanceOf(SynchronizationDataTransfer::class))
            ->willReturn('kv:search_ranking_configuration');

        $synchronizationServiceMock = $this->createMock(SearchRankingStorageToSynchronizationServiceInterface::class);
        $synchronizationServiceMock->method('getStorageKeyBuilder')->willReturn($keyGeneratorMock);

        return $synchronizationServiceMock;
    }

    protected function resetMemoizedCache(): void
    {
        $cacheProperty = new ReflectionProperty(ConfigurationStorageReader::class, 'rankingConfigurationCache');
        $cacheProperty->setValue(null, []);
    }
}
