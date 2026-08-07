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
 * The Scope Copy page: both the original (store,locale)-scoped weight/settings copy/lock actions, and
 * the store-only "Sync store configuration" action for formula/isActive/shape.
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
    public function scopeCopyPageLoadsWithBothCopyActions(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(ScopeCopyPage::URL);

        $i->seeElement(ScopeCopyPage::SELECTOR_SOURCE_STORE_SELECT);
        $i->seeElement(ScopeCopyPage::SELECTOR_SOURCE_LOCALE_SELECT);
        $i->seeElement(ScopeCopyPage::SELECTOR_TARGET_STORE_SELECT);
        $i->seeElement(ScopeCopyPage::SELECTOR_TARGET_LOCALE_SELECT);
        $i->see(ScopeCopyPage::COPY_NOW_BUTTON_TEXT);
        $i->see(ScopeCopyPage::LOCK_BUTTON_TEXT);

        // Store-only, no locale, deliberately no lock counterpart — formula/k tuning changes far less
        // often than weight, so a recurring sync would mostly re-copy an unchanged value (see the
        // Facade's own docblock).
        $i->see(ScopeCopyPage::SYNC_STORE_CONFIG_HEADING_TEXT);
        $i->seeElement(ScopeCopyPage::SELECTOR_SYNC_MODE_MIRROR);
        $i->seeElement(ScopeCopyPage::SELECTOR_SYNC_MODE_COPY_ONLY_OVERLAP);
        $i->see(ScopeCopyPage::SYNC_NOW_BUTTON_TEXT);
    }

    /**
     * Real round trip through the real Mirror-mode "Sync now" action, store DE -> store AT — the same
     * scope pair this package's whole store-scoped-formula migration used throughout its own live
     * verification. Re-syncing AT to match DE (its usual, already-mostly-matching state from that same
     * verification) is safe and repeatable, unlike testing against fresh/empty data this suite doesn't own.
     *
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     */
    public function syncStoreConfigMirrorModeSyncsFormulaFromSourceToTarget(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(sprintf(
            '%s?sourceStoreName=DE&sourceLocaleName=de_DE&targetStoreName=AT&targetLocaleName=de_DE',
            ScopeCopyPage::URL,
        ));

        // Mirror is the form's own default selection — only the overwrite confirmation needs an explicit
        // click, since AT already has its own saved store configuration from earlier in this migration.
        // scrollTo moves the checkbox away from the fixed Symfony debug toolbar's dead zone at this
        // viewport size (same fix SettingsCest uses) — the preview table above it pushes it right into
        // that zone otherwise.
        $i->scrollTo(ScopeCopyPage::SELECTOR_SYNC_CONFIRM_OVERWRITE);
        $i->checkOption(ScopeCopyPage::SELECTOR_SYNC_CONFIRM_OVERWRITE);
        $i->click(ScopeCopyPage::SYNC_NOW_BUTTON_TEXT);

        $i->see('Synced');
        $i->see('from DE to AT');
    }

    /**
     * The "Sync store configuration" widget has its OWN independent source/target store pickers,
     * deliberately separate from the (store,locale)-scoped ones in "Copy configuration between
     * store/locale scopes" above — no shared/duplicated dropdowns between the two widgets.
     *
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     */
    public function syncStoreConfigHasItsOwnIndependentStorePickers(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(ScopeCopyPage::URL);

        $i->seeElement(ScopeCopyPage::SELECTOR_SYNC_SOURCE_STORE_SELECT);
        $i->seeElement(ScopeCopyPage::SELECTOR_SYNC_TARGET_STORE_SELECT);
        $i->see(ScopeCopyPage::COPY_PREVIEW_HEADING_TEXT);
        $i->see(ScopeCopyPage::SYNC_PREVIEW_HEADING_TEXT);
    }

    /**
     * Changing either widget's picker must not reset the OTHER widget's current selection — each carries
     * the other's live values through as hidden fields specifically to prevent this (see the template).
     *
     * @param \SprykerCommunityTest\Zed\SearchRankingGuiPresentation\SearchRankingGuiPresentationTester $i
     */
    public function changingTheSyncPickersDoesNotResetTheCopyWidgetsPickers(SearchRankingGuiPresentationTester $i): void
    {
        $i->amOnPage(sprintf(
            '%s?sourceStoreName=DE&sourceLocaleName=de_DE&targetStoreName=AT&targetLocaleName=de_DE&syncSourceStoreName=DE&syncTargetStoreName=AT',
            ScopeCopyPage::URL,
        ));

        // Flips the sync widget's own source store — a real page reload via the select's onchange, not a
        // form submit shared with the widget above.
        $i->selectOption(ScopeCopyPage::SELECTOR_SYNC_SOURCE_STORE_SELECT, 'AT');

        $i->seeInField(ScopeCopyPage::SELECTOR_SOURCE_STORE_SELECT, 'DE');
        $i->seeInField(ScopeCopyPage::SELECTOR_SOURCE_LOCALE_SELECT, 'de_DE');
        $i->seeInField(ScopeCopyPage::SELECTOR_TARGET_STORE_SELECT, 'AT');
        $i->seeInField(ScopeCopyPage::SELECTOR_TARGET_LOCALE_SELECT, 'de_DE');
    }
}
