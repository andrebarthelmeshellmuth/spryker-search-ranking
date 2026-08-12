<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Plugin\Catalog;

use Codeception\Test\Unit;
use Elastica\Query;
use Elastica\Response;
use Elastica\Result;
use Elastica\ResultSet;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\StoreTransfer;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToLocaleClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToPermissionClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientInterface;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientInterface;
use SprykerCommunity\Client\SearchRanking\Plugin\Catalog\RandomImpactResultFormatterPlugin;
use SprykerCommunity\Client\SearchRanking\RandomImpact\RandomImpactCalculator;
use SprykerCommunity\Client\SearchRanking\SearchRankingFactory;
use SprykerCommunity\Shared\SearchRanking\Plugin\SeeSearchRankingRandomImpactPermissionPlugin;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Plugin
 * @group Catalog
 * @group RandomImpactResultFormatterPluginTest
 * @group NeedsSearch
 */
class RandomImpactResultFormatterPluginTest extends Unit
{
    /**
     * @var int
     */
    protected const ID_PRODUCT_ABSTRACT_ONE = 1;

    /**
     * @var int
     */
    protected const ID_PRODUCT_ABSTRACT_TWO = 2;

    public function testGetNameReturnsTheClassConstant(): void
    {
        $this->assertSame(RandomImpactResultFormatterPlugin::NAME, (new RandomImpactResultFormatterPlugin())->getName());
    }

    public function testFormatResultReturnsNoDataWithoutThePermission(): void
    {
        // Arrange
        $configurationTransfer = $this->createActiveConfigurationTransfer();
        $plugin = $this->createResultFormatterPlugin(false, $configurationTransfer);
        $resultSet = $this->createResultSet([
            $this->createHit(static::ID_PRODUCT_ABSTRACT_ONE, 0.90, 1.0),
            $this->createHit(static::ID_PRODUCT_ABSTRACT_TWO, 0.86, 0.0),
        ]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert
        $this->assertSame([], $result);
    }

    public function testFormatResultReturnsNoDataWhenNoRankingConfigurationIsSynchronized(): void
    {
        // Arrange
        $plugin = $this->createResultFormatterPlugin(true, null);
        $resultSet = $this->createResultSet([
            $this->createHit(static::ID_PRODUCT_ABSTRACT_ONE, 0.90, 1.0),
            $this->createHit(static::ID_PRODUCT_ABSTRACT_TWO, 0.86, 0.0),
        ]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert
        $this->assertSame([], $result);
    }

    public function testFormatResultReturnsIsActiveFalseAndNoDeltasWhenRandomHasNoWeight(): void
    {
        // Arrange
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setRelevanceWeight(0.75)
            ->setMetricWeights(['random' => 0.0, 'top_seller' => 1.0]);
        $plugin = $this->createResultFormatterPlugin(true, $configurationTransfer);
        $resultSet = $this->createResultSet([
            $this->createHit(static::ID_PRODUCT_ABSTRACT_ONE, 0.90, 1.0),
            $this->createHit(static::ID_PRODUCT_ABSTRACT_TWO, 0.86, 0.0),
        ]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert
        $this->assertFalse($result[SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE]);
        $this->assertSame([], $result[SharedSearchRankingConfig::RANDOM_IMPACT_KEY_DELTAS]);
    }

    /**
     * End-to-end through the real RandomImpactCalculator (not mocked) -- proves the plugin correctly reads
     * `_score` and `scores.<randomMetricName>` off each real Elastica hit and hands them to the calculator
     * in live display order.
     */
    public function testFormatResultReturnsTheRealComputedDeltasWhenActive(): void
    {
        // Arrange
        $configurationTransfer = $this->createActiveConfigurationTransfer();
        $plugin = $this->createResultFormatterPlugin(true, $configurationTransfer);
        $resultSet = $this->createResultSet([
            $this->createHit(static::ID_PRODUCT_ABSTRACT_ONE, 0.90, 1.0),
            $this->createHit(static::ID_PRODUCT_ABSTRACT_TWO, 0.86, 0.0),
        ]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert -- relevanceWeight=0.75, random weight=0.2 -> 0.25 * 0.2 = 0.05 per unit of randomSignal.
        // Product 1 loses 0.05 (0.90 -> 0.85), product 2 loses nothing (stays 0.86) -- product 2 overtakes.
        $this->assertTrue($result[SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE]);
        $this->assertSame(1, $result[SharedSearchRankingConfig::RANDOM_IMPACT_KEY_DELTAS][static::ID_PRODUCT_ABSTRACT_ONE]);
        $this->assertSame(-1, $result[SharedSearchRankingConfig::RANDOM_IMPACT_KEY_DELTAS][static::ID_PRODUCT_ABSTRACT_TWO]);
    }

    public function testFormatResultTreatsAHitWithNoScoresFieldAtAllAsARandomSignalOfZero(): void
    {
        // Arrange -- a product whose document predates the scores field being populated (or search-debug's
        // own source-whitelist plumbing wasn't reached) must not error, just contribute nothing.
        $configurationTransfer = $this->createActiveConfigurationTransfer();
        $plugin = $this->createResultFormatterPlugin(true, $configurationTransfer);
        $resultSet = new ResultSet(new Response('{}'), new Query(), [
            new Result([
                '_id' => (string)static::ID_PRODUCT_ABSTRACT_ONE,
                '_score' => 0.9,
                '_source' => [
                    'search-result-data' => ['id_product_abstract' => static::ID_PRODUCT_ABSTRACT_ONE],
                ],
            ]),
            $this->createHit(static::ID_PRODUCT_ABSTRACT_TWO, 0.86, 0.0),
        ]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert -- both hits effectively have randomSignal 0 -- no reordering, no deltas.
        $this->assertSame([], $result[SharedSearchRankingConfig::RANDOM_IMPACT_KEY_DELTAS]);
    }

    protected function createActiveConfigurationTransfer(): SearchRankingConfigurationStorageTransfer
    {
        return (new SearchRankingConfigurationStorageTransfer())
            ->setRandomMetricName('random')
            ->setRelevanceWeight(0.75)
            ->setMetricWeights(['random' => 0.2, 'top_seller' => 0.8]);
    }

    /**
     * @param int $idProductAbstract
     * @param float $score
     * @param float $randomSignal
     */
    protected function createHit(int $idProductAbstract, float $score, float $randomSignal): Result
    {
        return new Result([
            '_id' => (string)$idProductAbstract,
            '_score' => $score,
            '_source' => [
                'search-result-data' => ['id_product_abstract' => $idProductAbstract],
                SharedSearchRankingConfig::PAGE_INDEX_FIELD_SCORES => ['random' => $randomSignal],
            ],
        ]);
    }

    /**
     * @param array<\Elastica\Result> $results
     */
    protected function createResultSet(array $results): ResultSet
    {
        return new ResultSet(new Response('{}'), new Query(), $results);
    }

    /**
     * @param bool $hasPermission
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer|null $configurationTransfer
     */
    protected function createResultFormatterPlugin(
        bool $hasPermission,
        ?SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): RandomImpactResultFormatterPlugin {
        $permissionClientMock = $this->createMock(SearchRankingToPermissionClientInterface::class);
        $permissionClientMock->method('can')->with(SeeSearchRankingRandomImpactPermissionPlugin::KEY)->willReturn($hasPermission);

        $searchRankingStorageClientMock = $this->createMock(SearchRankingToSearchRankingStorageClientInterface::class);
        $searchRankingStorageClientMock->method('findRankingConfiguration')->with('DE', 'en_US')->willReturn($configurationTransfer);

        $storeClientMock = $this->createMock(SearchRankingToStoreClientInterface::class);
        $storeClientMock->method('getCurrentStore')->willReturn((new StoreTransfer())->setName('DE'));

        $localeClientMock = $this->createMock(SearchRankingToLocaleClientInterface::class);
        $localeClientMock->method('getCurrentLocale')->willReturn('en_US');

        $searchRankingFactoryMock = $this->getMockBuilder(SearchRankingFactory::class)
            ->onlyMethods([
                'getPermissionClient',
                'getSearchRankingStorageClient',
                'getStoreClient',
                'getLocaleClient',
                'createRandomImpactCalculator',
            ])
            ->getMock();
        $searchRankingFactoryMock->method('getPermissionClient')->willReturn($permissionClientMock);
        $searchRankingFactoryMock->method('getSearchRankingStorageClient')->willReturn($searchRankingStorageClientMock);
        $searchRankingFactoryMock->method('getStoreClient')->willReturn($storeClientMock);
        $searchRankingFactoryMock->method('getLocaleClient')->willReturn($localeClientMock);
        $searchRankingFactoryMock->method('createRandomImpactCalculator')->willReturn(new RandomImpactCalculator());

        $resultFormatterPlugin = new RandomImpactResultFormatterPlugin();
        $resultFormatterPlugin->setFactory($searchRankingFactoryMock);

        return $resultFormatterPlugin;
    }
}
