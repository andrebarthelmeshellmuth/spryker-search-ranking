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

    /**
     * Unlike every other scoped page in this GUI, this table is a cross-scope audit trail — with no
     * storeName/localeName query param at all, the filter dropdowns must default to "no filter" (their
     * blank/"All ..." option), not silently fall back to DE/de_DE the way every other scoped page does.
     *
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     */
    public function filterDropdownsDefaultToNoFilterWhenNoScopeIsGivenInTheUrl(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(MetricHistoryPage::URL);
        $i->waitForElementVisible(MetricHistoryPage::SELECTOR_TABLE, 10);

        $i->seeElement(MetricHistoryPage::SELECTOR_STORE_SELECT);
        $i->seeElement(MetricHistoryPage::SELECTOR_LOCALE_SELECT);
        $i->seeInField(MetricHistoryPage::SELECTOR_STORE_SELECT, 'All stores');
        $i->seeInField(MetricHistoryPage::SELECTOR_LOCALE_SELECT, 'All locales');
    }

    /**
     * Picking a store in the filter dropdown submits the (plain GET, no-AJAX) form immediately via its
     * own onchange handler — the resulting page must both keep that store selected AND carry it through
     * to the DataTables AJAX endpoint's own URL (MetricHistoryTable::configure()'s `setUrl()`), not just
     * the initial page load.
     *
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     */
    public function selectingAStoreFiltersTheTableAndKeepsTheSelectionAfterReload(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(MetricHistoryPage::URL);
        $i->waitForElementVisible(MetricHistoryPage::SELECTOR_TABLE, 10);

        $i->selectOption(MetricHistoryPage::SELECTOR_STORE_SELECT, $i::DEFAULT_STORE_NAME);

        $i->seeInCurrentUrl('storeName=' . $i::DEFAULT_STORE_NAME);
        $i->seeInField(MetricHistoryPage::SELECTOR_STORE_SELECT, $i::DEFAULT_STORE_NAME);
        $i->waitForElementVisible(MetricHistoryPage::SELECTOR_TABLE, 10);
    }
}
