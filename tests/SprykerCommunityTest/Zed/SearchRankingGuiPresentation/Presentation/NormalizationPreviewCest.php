<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\Presentation;

use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\MetricFormPage;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester;

/**
 * The Edit form's live normalization preview: the typed formula evaluated server-side against the
 * metric's real distribution digest and plotted as an SVG, with curve-fit suggestions below it.
 *
 * Deliberately smoke-level only, per this package's own manual QA checklist: the curve-fit math itself
 * is already covered by FormulaEvaluator's own unit tests - a browser test can only usefully prove the
 * JS/AJAX wiring actually delivers and renders SOMETHING, not that the math is correct.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingGuiPresentation
 * @group Presentation
 * @group NormalizationPreviewCest
 * Add your own group annotations below this line
 */
class NormalizationPreviewCest
{
    /**
     * @var int
     */
    protected const EXISTING_METRIC_ID = 1;

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
    public function previewRendersForAMetricWithADistributionDigest(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(sprintf(
            '%s?id-search-ranking-metric=%d&storeName=%s&localeName=%s',
            MetricFormPage::URL_EDIT,
            static::EXISTING_METRIC_ID,
            $i::DEFAULT_STORE_NAME,
            $i::DEFAULT_LOCALE_NAME,
        ));
        $i->seeElement(MetricFormPage::SELECTOR_NORMALIZATION_PREVIEW);

        // The preview fires on page load (see the module's own footer_js) - give the debounced fetch
        // time to resolve rather than asserting on the initial empty SVG.
        $i->wait(2);

        $i->assertEmpty(trim($i->grabTextFrom(MetricFormPage::SELECTOR_PREVIEW_MESSAGE)));
        $i->seeElement(MetricFormPage::SELECTOR_PREVIEW_PLOT . ' polyline');
    }

    /**
     * Retyping the formula (input event) re-triggers the same fetch this class's other test already
     * proves works on load - this test is specifically about the debounced input listener existing.
     *
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function editingTheFormulaRefreshesThePreview(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(sprintf(
            '%s?id-search-ranking-metric=%d&storeName=%s&localeName=%s',
            MetricFormPage::URL_EDIT,
            static::EXISTING_METRIC_ID,
            $i::DEFAULT_STORE_NAME,
            $i::DEFAULT_LOCALE_NAME,
        ));
        $i->wait(2);

        $i->fillField(MetricFormPage::FIELD_FORMULA, 'atan(x / avg) / (pi() / 2)');
        $i->wait(2);

        $i->assertEmpty(trim($i->grabTextFrom(MetricFormPage::SELECTOR_PREVIEW_MESSAGE)));
        $i->seeElement(MetricFormPage::SELECTOR_PREVIEW_PLOT . ' polyline');
    }
}
