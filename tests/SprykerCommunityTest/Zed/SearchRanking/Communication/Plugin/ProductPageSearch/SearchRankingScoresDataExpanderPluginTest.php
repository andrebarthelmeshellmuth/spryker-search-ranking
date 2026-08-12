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
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingScoresDataExpanderPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Plugin
 * @group ProductPageSearch
 * @group SearchRankingScoresDataExpanderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group Portable
 */
class SearchRankingScoresDataExpanderPluginTest extends Unit
{
    public function testCopiesTheNormalizedScoresFromThePayloadOntoTheSearchTransfer(): void
    {
        // Arrange
        $productPayloadTransfer = (new ProductPayloadTransfer())
            ->setSearchRankingScores(['top_seller' => 0.51, 'pdp_impressions' => 0.2]);
        $productData = [ProductPageSearchConfig::PRODUCT_ABSTRACT_PAGE_LOAD_DATA => $productPayloadTransfer];
        $productAbstractPageSearchTransfer = new ProductPageSearchTransfer();

        // Act
        (new SearchRankingScoresDataExpanderPlugin())->expandProductPageData($productData, $productAbstractPageSearchTransfer);

        // Assert
        $this->assertSame(
            ['top_seller' => 0.51, 'pdp_impressions' => 0.2],
            $productAbstractPageSearchTransfer->getScores(),
        );
    }

    /**
     * A product without any ranking scores must still set an (empty) scores array rather than leaving
     * the field untouched.
     */
    public function testSetsAnEmptyScoresArrayWhenThePayloadHasNoScores(): void
    {
        // Arrange
        $productPayloadTransfer = new ProductPayloadTransfer();
        $productData = [ProductPageSearchConfig::PRODUCT_ABSTRACT_PAGE_LOAD_DATA => $productPayloadTransfer];
        $productAbstractPageSearchTransfer = new ProductPageSearchTransfer();

        // Act
        (new SearchRankingScoresDataExpanderPlugin())->expandProductPageData($productData, $productAbstractPageSearchTransfer);

        // Assert
        $this->assertSame([], $productAbstractPageSearchTransfer->getScores());
    }
}
