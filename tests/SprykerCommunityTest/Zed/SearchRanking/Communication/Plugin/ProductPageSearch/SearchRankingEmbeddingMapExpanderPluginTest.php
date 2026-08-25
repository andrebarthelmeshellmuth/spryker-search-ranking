<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Plugin\ProductPageSearch;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\PageMapTransfer;
use Spryker\Zed\ProductPageSearchExtension\Dependency\PageMapBuilderInterface;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingEmbeddingMapExpanderPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Plugin
 * @group ProductPageSearch
 * @group SearchRankingEmbeddingMapExpanderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group Portable
 */
class SearchRankingEmbeddingMapExpanderPluginTest extends Unit
{
    public function testWritesTheEmbeddingFieldWhenTheProductDataHoldsAVector(): void
    {
        // Arrange
        $productData = [SharedSearchRankingConfig::PAGE_INDEX_FIELD_EMBEDDING => [0.1, 0.2, 0.3]];
        $pageMapTransfer = new PageMapTransfer();

        // Act
        $result = (new SearchRankingEmbeddingMapExpanderPlugin())->expandProductMap(
            $pageMapTransfer,
            $this->createMock(PageMapBuilderInterface::class),
            $productData,
            new LocaleTransfer(),
        );

        // Assert
        $this->assertSame([0.1, 0.2, 0.3], $result->getEmbedding());
    }

    /**
     * Products without a stored vector must get no `embedding` field at all on the document, rather than
     * an empty or zero one — see the plugin's own docblock.
     */
    public function testLeavesTheEmbeddingFieldUnsetWhenTheProductDataHasNoVector(): void
    {
        // Arrange
        $pageMapTransfer = new PageMapTransfer();

        // Act
        $result = (new SearchRankingEmbeddingMapExpanderPlugin())->expandProductMap(
            $pageMapTransfer,
            $this->createMock(PageMapBuilderInterface::class),
            [],
            new LocaleTransfer(),
        );

        // Assert
        $this->assertArrayNotHasKey('embedding', $result->modifiedToArray(true, true));
    }
}
