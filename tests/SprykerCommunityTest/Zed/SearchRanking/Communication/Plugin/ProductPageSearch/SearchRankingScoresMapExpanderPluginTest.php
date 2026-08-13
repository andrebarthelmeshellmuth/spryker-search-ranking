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
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingScoresMapExpanderPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Plugin
 * @group ProductPageSearch
 * @group SearchRankingScoresMapExpanderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 * @group Portable
 */
class SearchRankingScoresMapExpanderPluginTest extends Unit
{
    public function testWritesTheScoresFieldWhenTheProductDataHoldsScores(): void
    {
        // Arrange
        $productData = [SharedSearchRankingConfig::PAGE_INDEX_FIELD_SCORES => ['top_seller' => 0.51]];
        $pageMapTransfer = new PageMapTransfer();

        // Act
        $result = (new SearchRankingScoresMapExpanderPlugin())->expandProductMap(
            $pageMapTransfer,
            $this->createMock(PageMapBuilderInterface::class),
            $productData,
            new LocaleTransfer(),
        );

        // Assert
        $this->assertSame(['top_seller' => 0.51], $result->getScores());
    }

    /**
     * Products without scores must get no `scores` field at all on the document, rather than an empty
     * one — see the plugin's own docblock. `getScores()` alone can't tell "never set" apart from "set to
     * []" since the transfer already defaults to [], so this asserts via `modifiedToArray()` (only
     * explicitly-set fields) instead.
     */
    public function testLeavesTheScoresFieldUnsetWhenTheProductDataHasNoScores(): void
    {
        // Arrange
        $pageMapTransfer = new PageMapTransfer();

        // Act
        $result = (new SearchRankingScoresMapExpanderPlugin())->expandProductMap(
            $pageMapTransfer,
            $this->createMock(PageMapBuilderInterface::class),
            [],
            new LocaleTransfer(),
        );

        // Assert
        $this->assertSame([], $result->getScores());
        $this->assertArrayNotHasKey('scores', $result->modifiedToArray(true, true));
    }
}
