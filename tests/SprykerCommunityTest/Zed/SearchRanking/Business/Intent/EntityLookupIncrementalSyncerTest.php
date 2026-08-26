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
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupIncrementalSyncer;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupSuggestIndexNameResolverInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\IncrementalEntityCorpusReaderPluginInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\ProductAbstractStoreResolverInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupIndexerInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Intent
 * @group EntityLookupIncrementalSyncerTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group Portable
 */
class EntityLookupIncrementalSyncerTest extends Unit
{
    /**
     * @var string
     */
    protected const STORE_NAME = 'DE';

    /**
     * @var int
     */
    protected const ID_STORE = 1;

    /**
     * @var int
     */
    protected const ID_PRODUCT_ABSTRACT = 42;

    /**
     * @var string
     */
    protected const INDEX_NAME = 'de_entity_lookup';

    public function testUpsertsTermsWhenTheProductIsActive(): void
    {
        // Arrange
        $indexerMock = $this->createMock(SuggestIndexEntityLookupIndexerInterface::class);
        $indexerMock->expects($this->once())
            ->method('upsertTerms')
            ->with(static::INDEX_NAME, 'sku', ['SKU-1']);
        $indexerMock->expects($this->never())->method('removeTerms');

        $plugin = $this->createIncrementalPluginMock('sku', ['SKU-1'], isActive: true);

        // Act
        $this->createSyncer([$plugin], $indexerMock)->sync([static::ID_PRODUCT_ABSTRACT]);
    }

    public function testRemovesTermsWhenTheProductIsInactiveAndTheTermIsNotUsedElsewhere(): void
    {
        // Arrange — mirrors a SKU: unique to one product, so removal is unconditionally safe.
        $indexerMock = $this->createMock(SuggestIndexEntityLookupIndexerInterface::class);
        $indexerMock->expects($this->never())->method('upsertTerms');
        $indexerMock->expects($this->once())
            ->method('removeTerms')
            ->with(static::INDEX_NAME, 'sku', ['SKU-1']);

        $plugin = $this->createIncrementalPluginMock('sku', ['SKU-1'], isActive: false, isStillUsedElsewhere: false);

        // Act
        $this->createSyncer([$plugin], $indexerMock)->sync([static::ID_PRODUCT_ABSTRACT]);
    }

    public function testDoesNotRemoveASharedTermStillUsedByAnotherActiveProduct(): void
    {
        // Arrange — mirrors brand/category: shared, so a deactivation must not blindly delete the term.
        $indexerMock = $this->createMock(SuggestIndexEntityLookupIndexerInterface::class);
        $indexerMock->expects($this->never())->method('upsertTerms');
        $indexerMock->expects($this->never())->method('removeTerms');

        $plugin = $this->createIncrementalPluginMock('brand', ['Bosch'], isActive: false, isStillUsedElsewhere: true);

        // Act
        $this->createSyncer([$plugin], $indexerMock)->sync([static::ID_PRODUCT_ABSTRACT]);
    }

    public function testSkipsAPluginThatDoesNotImplementTheIncrementalInterface(): void
    {
        // Arrange
        $indexerMock = $this->createMock(SuggestIndexEntityLookupIndexerInterface::class);
        $indexerMock->expects($this->never())->method('upsertTerms');
        $indexerMock->expects($this->never())->method('removeTerms');
        $indexerMock->expects($this->never())->method('ensureIndexExists');

        $nonIncrementalPlugin = $this->createMock(EntityCorpusReaderPluginInterface::class);
        $nonIncrementalPlugin->method('getEntityType')->willReturn('myproject_manufacturer_part_number');

        // Act
        $this->createSyncer([$nonIncrementalPlugin], $indexerMock)->sync([static::ID_PRODUCT_ABSTRACT]);
    }

    public function testIsANoOpForAnEmptyProductAbstractIdList(): void
    {
        // Arrange
        $indexerMock = $this->createMock(SuggestIndexEntityLookupIndexerInterface::class);
        $indexerMock->expects($this->never())->method('ensureIndexExists');

        $plugin = $this->createIncrementalPluginMock('sku', ['SKU-1'], isActive: true);

        // Act
        $this->createSyncer([$plugin], $indexerMock)->sync([]);
    }

    /**
     * @param array<\SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface> $entityCorpusReaderPlugins
     * @param \PHPUnit\Framework\MockObject\MockObject|\SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupIndexerInterface $indexerMock
     */
    protected function createSyncer(array $entityCorpusReaderPlugins, $indexerMock): EntityLookupIncrementalSyncer
    {
        $storeFacadeMock = $this->createMock(SearchRankingToStoreFacadeInterface::class);
        $storeFacadeMock->method('getAllStores')->willReturn([
            (new StoreTransfer())->setName(static::STORE_NAME)->setIdStore(static::ID_STORE),
        ]);

        $indexNameResolverMock = $this->createMock(EntityLookupSuggestIndexNameResolverInterface::class);
        $indexNameResolverMock->method('resolveIndexName')->with(static::STORE_NAME)->willReturn(static::INDEX_NAME);

        $productAbstractStoreResolverMock = $this->createMock(ProductAbstractStoreResolverInterface::class);
        $productAbstractStoreResolverMock->method('getIdStoresForProductAbstract')
            ->with(static::ID_PRODUCT_ABSTRACT)
            ->willReturn([static::ID_STORE]);

        return new EntityLookupIncrementalSyncer(
            $entityCorpusReaderPlugins,
            $indexerMock,
            $storeFacadeMock,
            $indexNameResolverMock,
            $productAbstractStoreResolverMock,
        );
    }

    /**
     * @param string $entityType
     * @param array<int, string> $terms
     * @param bool $isActive
     * @param bool $isStillUsedElsewhere
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|\SprykerCommunity\Zed\SearchRanking\Business\Intent\IncrementalEntityCorpusReaderPluginInterface
     */
    protected function createIncrementalPluginMock(
        string $entityType,
        array $terms,
        bool $isActive,
        bool $isStillUsedElsewhere = false,
    ): IncrementalEntityCorpusReaderPluginInterface {
        $pluginMock = $this->createMock(IncrementalEntityCorpusReaderPluginInterface::class);
        $pluginMock->method('getEntityType')->willReturn($entityType);
        $pluginMock->method('getTermsForProductAbstract')->willReturn($terms);
        $pluginMock->method('isProductAbstractActive')->willReturn($isActive);
        $pluginMock->method('isTermStillUsedElsewhere')->willReturn($isStillUsedElsewhere);

        return $pluginMock;
    }
}
