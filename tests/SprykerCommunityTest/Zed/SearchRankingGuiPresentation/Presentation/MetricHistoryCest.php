<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\Presentation;

use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\MetricHistoryPage;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester;

/**
 * Metric History: every recorded metric config change, newest first.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingGuiPresentation
 * @group Presentation
 * @group MetricHistoryCest
 * Add your own group annotations below this line
 */
class MetricHistoryCest
{
    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     */
    public function _before(SearchRankingGuiPresentationTester $i): void
    {
        $i->amZed();
        $i->amLoggedInUser();
    }

    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     */
    public function historyTableLoads(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(MetricHistoryPage::URL);
        $i->waitForElementVisible(MetricHistoryPage::SELECTOR_TABLE, 10);
        // The "snapshot, not a diff" explainer banner - real content, not filler.
        $i->see('snapshot of a metric');
    }
}
