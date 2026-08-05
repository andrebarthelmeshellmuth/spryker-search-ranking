<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\Presentation;

use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\ProductMetricPage;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester;

/**
 * Product Ranking Values (raw + normalized per product) and its Gaps view (products with no value at
 * all for a business score).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingGuiPresentation
 * @group Presentation
 * @group ProductMetricCest
 * Add your own group annotations below this line
 */
class ProductMetricCest
{
    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function _before(SearchRankingGuiPresentationTester $i): void
    {
        $i->amZed();
        $i->amLoggedInUser();
    }

    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function productValuesTableLoads(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(ProductMetricPage::URL . '?storeName=' . $i::DEFAULT_STORE_NAME . '&localeName=' . $i::DEFAULT_LOCALE_NAME);
        $i->waitForElementVisible(ProductMetricPage::SELECTOR_TABLE, 10);
        $i->see(ProductMetricPage::VIEW_GAPS_LINK_TEXT);
    }

    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function viewGapsLinkOpensTheGapsPageWithTheSameScope(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(ProductMetricPage::URL . '?storeName=' . $i::DEFAULT_STORE_NAME . '&localeName=' . $i::DEFAULT_LOCALE_NAME);
        $i->waitForElementVisible(ProductMetricPage::SELECTOR_TABLE, 10);
        $i->click(ProductMetricPage::VIEW_GAPS_LINK_TEXT);

        $i->seeInCurrentUrl(ProductMetricPage::URL_GAP);
        $i->seeElement(ProductMetricPage::SELECTOR_METRIC_SELECT);
        $i->waitForElementVisible(ProductMetricPage::SELECTOR_TABLE, 10);
    }

    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function gapsPageFiltersByMetric(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(ProductMetricPage::URL_GAP . '?storeName=' . $i::DEFAULT_STORE_NAME . '&localeName=' . $i::DEFAULT_LOCALE_NAME);
        $i->waitForElementVisible(ProductMetricPage::SELECTOR_METRIC_SELECT, 10);

        $options = $i->grabMultiple(ProductMetricPage::SELECTOR_METRIC_SELECT . ' option', 'value');
        $realMetricOptions = array_filter($options, static fn (string $value): bool => $value !== '');

        if ($realMetricOptions === []) {
            $i->comment('No metrics configured in this scope to filter by; skipping.');

            return;
        }

        $i->selectOption(ProductMetricPage::SELECTOR_METRIC_SELECT, reset($realMetricOptions));
        $i->waitForElementVisible(ProductMetricPage::SELECTOR_TABLE, 10);
    }
}
