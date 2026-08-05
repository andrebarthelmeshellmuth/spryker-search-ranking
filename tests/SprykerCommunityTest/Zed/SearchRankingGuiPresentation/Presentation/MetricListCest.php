<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\Presentation;

use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\MetricFormPage;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\MetricListPage;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester;

/**
 * List of Ranking Metrics: the store/locale-scoped table, and the full create -> edit -> delete round
 * trip through the real forms - the same shape as core's own product-search
 * SearchPreferencesCest/FilterPreferencesCest.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingGuiPresentation
 * @group Presentation
 * @group MetricListCest
 * Add your own group annotations below this line
 */
class MetricListCest
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
    public function listOfMetricsLoadsScopedByStoreAndLocale(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage($i->scopedListUrl());
        $i->waitForElementVisible(MetricListPage::SELECTOR_TABLE, 10);
        $i->seeElement(MetricListPage::SELECTOR_STORE_SELECT);
        $i->seeElement(MetricListPage::SELECTOR_LOCALE_SELECT);
        $i->see(MetricListPage::CREATE_METRIC_LINK_TEXT);
    }

    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function createEditAndDeleteMetricRoundTrip(SearchRankingGuiPresentationTester $i): void
    {
        $name = 'presentation_test_' . substr(md5(uniqid('', true)), 0, 8);

        $idSearchRankingMetric = $i->createMetric($name, 0.1, 'atan(x / avg) / (pi() / 2)');
        $i->assertGreaterThan(0, $idSearchRankingMetric);
        $i->see($name);

        $i->amOnPage(sprintf(
            '%s?id-search-ranking-metric=%d&storeName=%s&localeName=%s',
            MetricFormPage::URL_EDIT,
            $idSearchRankingMetric,
            $i::DEFAULT_STORE_NAME,
            $i::DEFAULT_LOCALE_NAME,
        ));
        $i->seeInField('#' . MetricFormPage::FIELD_NAME, $name);
        $i->fillField('#' . MetricFormPage::FIELD_WEIGHT, '0.2');
        $i->click(MetricFormPage::SELECTOR_SUBMIT);
        $i->see(sprintf(MetricFormPage::FLASH_MESSAGE_UPDATED_FORMAT, $name));

        $i->deleteMetric($idSearchRankingMetric);
        $i->dontSee($name);
    }

    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function normalizeActiveWeightsButtonWorks(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage($i->scopedListUrl());
        $i->click(MetricListPage::NORMALIZE_WEIGHTS_BUTTON_TEXT);
        // Two valid outcomes depending on whether the active weights already summed to 1 - both are
        // real success states, see NormalizeWeightsController.
        $i->assertTrue(
            $i->tryToSeeElement("//*[contains(text(), 'Active metric weights were normalized to sum to 1.')]")
            || $i->tryToSeeElement("//*[contains(text(), 'already sum to 1')]"),
        );
    }
}
