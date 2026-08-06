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
 * Phase 7 of the store-scoped-formula migration's own store-only "Sync store configuration" action (see
 * project memory) — the one part of that whole migration that had never gotten Presentation coverage,
 * only manual chrome-devtools MCP verification at build time.
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

        // Phase 7's own addition to this page — store-only, no locale, deliberately no lock counterpart
        // (see that phase's own README/Facade docblock note for why).
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
        $i->checkOption(ScopeCopyPage::SELECTOR_SYNC_CONFIRM_OVERWRITE);
        $i->click(ScopeCopyPage::SYNC_NOW_BUTTON_TEXT);

        $i->see('Synced');
        $i->see('from DE to AT');
    }
}
