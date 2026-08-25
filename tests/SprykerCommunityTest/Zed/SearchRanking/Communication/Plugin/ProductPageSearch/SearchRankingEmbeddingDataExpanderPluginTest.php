<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Plugin\ProductPageSearch;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ProductPageSearchTransfer;
use Generated\Shared\Transfer\ProductPayloadTransfer;
use Spryker\Shared\ProductPageSearch\ProductPageSearchConfig;
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingEmbeddingDataExpanderPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Plugin
 * @group ProductPageSearch
 * @group SearchRankingEmbeddingDataExpanderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group Portable
 */
class SearchRankingEmbeddingDataExpanderPluginTest extends Unit
{
    public function testCopiesTheEmbeddingFromThePayloadOntoTheSearchTransfer(): void
    {
        // Arrange
        $productPayloadTransfer = (new ProductPayloadTransfer())->setEmbedding([0.1, 0.2, 0.3]);
        $productData = [ProductPageSearchConfig::PRODUCT_ABSTRACT_PAGE_LOAD_DATA => $productPayloadTransfer];
        $productAbstractPageSearchTransfer = new ProductPageSearchTransfer();

        // Act
        (new SearchRankingEmbeddingDataExpanderPlugin())->expandProductPageData($productData, $productAbstractPageSearchTransfer);

        // Assert
        $this->assertSame([0.1, 0.2, 0.3], $productAbstractPageSearchTransfer->getEmbedding());
    }

    /**
     * A product without a stored embedding must get NO embedding field at all, unlike scores (which
     * always get an explicit `[]`) — an empty/zero vector is not a meaningful default here.
     */
    public function testLeavesTheEmbeddingFieldUnsetWhenThePayloadHasNoEmbedding(): void
    {
        // Arrange
        $productPayloadTransfer = new ProductPayloadTransfer();
        $productData = [ProductPageSearchConfig::PRODUCT_ABSTRACT_PAGE_LOAD_DATA => $productPayloadTransfer];
        $productAbstractPageSearchTransfer = new ProductPageSearchTransfer();

        // Act
        (new SearchRankingEmbeddingDataExpanderPlugin())->expandProductPageData($productData, $productAbstractPageSearchTransfer);

        // Assert
        $this->assertArrayNotHasKey('embedding', $productAbstractPageSearchTransfer->modifiedToArray(true, true));
    }
}
