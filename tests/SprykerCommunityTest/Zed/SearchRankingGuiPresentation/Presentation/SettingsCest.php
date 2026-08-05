<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\Presentation;

use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\SettingsPage;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester;

/**
 * Ranking Formula Settings: the relevance-weight/saturation-point form driving the blend formula.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingGuiPresentation
 * @group Presentation
 * @group SettingsCest
 * Add your own group annotations below this line
 */
class SettingsCest
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
        $i->amOnPage(SettingsPage::URL . '?storeName=' . $i::DEFAULT_STORE_NAME . '&localeName=' . $i::DEFAULT_LOCALE_NAME);
    }

    /**
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function everyConfiguredFieldIsPresent(SearchRankingGuiPresentationTester $i): void
    {
        $i->seeElement('#' . SettingsPage::FIELD_RELEVANCE_WEIGHT);
        $i->seeElement('#' . SettingsPage::FIELD_RELEVANCE_SATURATION_POINT);
        $i->seeElement('#' . SettingsPage::FIELD_SPECIFICITY_BLEND_WEIGHT);
        $i->seeElement('#' . SettingsPage::FIELD_SPECIFICITY_SATURATION_POINT);
        $i->seeElement('#' . SettingsPage::FIELD_SPECIFICITY_WEIGHT_EXPONENT);
        $i->seeElement('#' . SettingsPage::FIELD_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE);
    }

    /**
     * Re-submits the form's OWN current values rather than new ones - this shop's real formula
     * settings are live production-ish config for a demo shop, so this test proves the save round trip
     * works without actually changing the configured behavior.
     *
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     *
     * @return void
     */
    public function savingTheFormShowsTheSuccessMessage(SearchRankingGuiPresentationTester $i): void
    {
        $i->click(SettingsPage::SELECTOR_SUBMIT);
        $i->see(SettingsPage::FLASH_MESSAGE_SAVED);
    }
}
