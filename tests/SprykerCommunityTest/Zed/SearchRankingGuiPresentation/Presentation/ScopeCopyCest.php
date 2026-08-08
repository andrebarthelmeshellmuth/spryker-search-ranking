<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\Presentation;

use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\ScopeCopyPage;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester;

/**
 * The Scope Copy page's single combined widget — one store+locale picker pair driving both the "Copy now"
 * (weight/setting/formula/isActive/shape, mode-selectable) and "Lock" (weight/setting only, kept in sync
 * daily) actions. Formula/isActive/shape are bootstrapped once by either action but never re-synced by the
 * daily cron — see the page's own "Kept in sync by Lock?" preview column.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingGuiPresentation
 * @group Presentation
 * @group ScopeCopyCest
 * Add your own group annotations below this line
 */
class ScopeCopyCest
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
    public function scopeCopyPageLoadsWithOneSharedPickerAndBothActions(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(ScopeCopyPage::URL);

        $i->seeElement(ScopeCopyPage::SELECTOR_SOURCE_STORE_SELECT);
        $i->seeElement(ScopeCopyPage::SELECTOR_SOURCE_LOCALE_SELECT);
        $i->seeElement(ScopeCopyPage::SELECTOR_TARGET_STORE_SELECT);
        $i->seeElement(ScopeCopyPage::SELECTOR_TARGET_LOCALE_SELECT);

        $i->see(ScopeCopyPage::COPY_PREVIEW_HEADING_TEXT);
        $i->see(ScopeCopyPage::KEPT_IN_SYNC_BY_LOCK_HEADING_TEXT);

        $i->seeElement(ScopeCopyPage::SELECTOR_COPY_MODE_MIRROR);
        $i->seeElement(ScopeCopyPage::SELECTOR_COPY_MODE_COPY_ONLY_OVERLAP);
        $i->seeElement(ScopeCopyPage::SELECTOR_COPY_CONFIRM_OVERWRITE);
        $i->see(ScopeCopyPage::COPY_NOW_BUTTON_TEXT);

        $i->seeElement(ScopeCopyPage::SELECTOR_LOCK_CONFIRM_OVERWRITE);
        $i->see(ScopeCopyPage::LOCK_BUTTON_TEXT);
    }

    /**
     * Real round trip through the real Mirror-mode "Copy now" action, store DE -> store AT — the same
     * scope pair this package's whole store-scoped-formula migration used throughout its own live
     * verification. Re-copying AT to match DE (its usual, already-mostly-matching state from that same
     * verification) is safe and repeatable, unlike testing against fresh/empty data this suite doesn't own.
     *
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     */
    public function copyNowMirrorModeCopiesWeightSettingAndFormulaFromSourceToTarget(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(sprintf(
            '%s?sourceStoreName=DE&sourceLocaleName=de_DE&targetStoreName=AT&targetLocaleName=de_DE',
            ScopeCopyPage::URL,
        ));

        // Mirror is the form's own default selection — only the overwrite confirmation needs an explicit
        // click, since AT already has its own saved configuration from earlier in this migration.
        // scrollTo moves the checkbox away from the fixed Symfony debug toolbar's dead zone at this
        // viewport size (same fix SettingsCest uses) — the preview table above it pushes it right into
        // that zone otherwise.
        $i->scrollTo(ScopeCopyPage::SELECTOR_COPY_CONFIRM_OVERWRITE);
        $i->checkOption(ScopeCopyPage::SELECTOR_COPY_CONFIRM_OVERWRITE);
        $i->click(ScopeCopyPage::COPY_NOW_BUTTON_TEXT);

        $i->see('Copied');
        $i->see('from DE/de_DE to AT/de_DE');
    }
}
