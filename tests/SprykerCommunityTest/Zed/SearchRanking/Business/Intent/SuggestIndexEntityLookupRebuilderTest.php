<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Intent;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StoreTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupSuggestIndexNameResolverInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupIndexerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupRebuilder;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToLocaleFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Intent
 * @group SuggestIndexEntityLookupRebuilderTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group Portable
 */
class SuggestIndexEntityLookupRebuilderTest extends Unit
{
    /**
     * @var string
     */
    protected const STORE_NAME = 'DE';

    /**
     * @var int
     */
    protected const ID_STORE = 1;

    public function testRoutesToTheRegisteredPluginMatchingTheRequestedEntityType(): void
    {
        // Arrange — a project's OWN plugin, registered alongside a built-in-shaped one, must be reachable
        // purely via its own getEntityType() — no core-package match()/hardcoded dispatch involved.
        $skuPlugin = $this->createEntityCorpusReaderPluginMock('sku', ['SKU-1', 'SKU-2']);
        $projectPlugin = $this->createEntityCorpusReaderPluginMock('myproject_manufacturer_part_number', ['MPN-1']);

        $rebuilder = $this->createRebuilder([$skuPlugin, $projectPlugin]);

        // Act
        $writtenCounts = $rebuilder->rebuild('myproject_manufacturer_part_number', static::STORE_NAME, null);

        // Assert
        $this->assertSame([static::STORE_NAME => 1], $writtenCounts);
    }

    public function testWritesNothingAndReturnsAnEmptyArrayForATypeNoRegisteredPluginMatches(): void
    {
        // Arrange
        $skuPlugin = $this->createEntityCorpusReaderPluginMock('sku', ['SKU-1']);
        $rebuilder = $this->createRebuilder([$skuPlugin]);

        // Act
        $writtenCounts = $rebuilder->rebuild('unknown_type', static::STORE_NAME, null);

        // Assert
        $this->assertSame([], $writtenCounts);
    }

    public function testDoesNotCallAPluginWhoseEntityTypeDoesNotMatchTheRequestedType(): void
    {
        // Arrange
        $skuPlugin = $this->createEntityCorpusReaderPluginMock('sku', ['SKU-1']);
        $skuPlugin->expects($this->never())->method('getTerms');

        $brandPlugin = $this->createEntityCorpusReaderPluginMock('brand', ['Brand-1']);

        $rebuilder = $this->createRebuilder([$skuPlugin, $brandPlugin]);

        // Act
        $rebuilder->rebuild('brand', static::STORE_NAME, null);
    }

    /**
     * @param array<\PHPUnit\Framework\MockObject\MockObject|\SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface> $entityCorpusReaderPlugins
     */
    protected function createRebuilder(array $entityCorpusReaderPlugins): SuggestIndexEntityLookupRebuilder
    {
        $indexerMock = $this->createMock(SuggestIndexEntityLookupIndexerInterface::class);
        $indexerMock->method('replaceTerms')->willReturnCallback(
            /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter */
            static fn (string $indexName, string $type, array $terms): int => count($terms),
        );

        $storeFacadeMock = $this->createMock(SearchRankingToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())->setName(static::STORE_NAME)->setIdStore(static::ID_STORE),
        ]);

        $localeFacadeMock = $this->createMock(SearchRankingToLocaleFacadeInterface::class);
        $localeFacadeMock->method('getLocaleCollection')->willReturn([]);

        $indexNameResolverMock = $this->createMock(EntityLookupSuggestIndexNameResolverInterface::class);
        $indexNameResolverMock->method('resolveIndexName')->willReturnCallback(
            static fn (string $storeName): string => mb_strtolower($storeName) . '_entity_lookup',
        );

        return new SuggestIndexEntityLookupRebuilder(
            $entityCorpusReaderPlugins,
            $indexerMock,
            $storeFacadeMock,
            $localeFacadeMock,
            $indexNameResolverMock,
        );
    }

    /**
     * @param string $entityType
     * @param array<int, string> $terms
     */
    protected function createEntityCorpusReaderPluginMock(string $entityType, array $terms): EntityCorpusReaderPluginInterface
    {
        $pluginMock = $this->createMock(EntityCorpusReaderPluginInterface::class);
        $pluginMock->method('getEntityType')->willReturn($entityType);
        $pluginMock->method('getTerms')->willReturn($terms);

        return $pluginMock;
    }
}
