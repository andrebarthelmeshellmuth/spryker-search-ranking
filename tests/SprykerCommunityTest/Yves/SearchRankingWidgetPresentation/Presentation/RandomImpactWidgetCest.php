<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester;

/**
 * The "Show random impact" checkbox and the per-product +X/-X badges it reveals. Deltas are real, live
 * data (this demoshop's own `random` metric weight and today's randomized signal values, refreshed daily
 * by the `search-ranking:randomize` cron) — this suite deliberately asserts only "at least one badge
 * becomes visible", never a specific delta value or count, so it stays green across that daily reshuffle.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchRankingWidgetPresentation
 * @group Presentation
 * @group RandomImpactWidgetCest
 * Add your own group annotations below this line
 */
class RandomImpactWidgetCest
{
    /**
     * @param \SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester $i
     */
    public function _before(SearchRankingWidgetPresentationTester $i): void
    {
        $i->amYves();
        $i->loginAsCustomer(SearchRankingWidgetPresentationTester::PERMITTED_CUSTOMER_EMAIL);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester $i
     */
    public function badgesAreHiddenUntilTheCheckboxIsChecked(SearchRankingWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX, 10);

        // Computed server-side up front (permission-gated), but CSS-hidden client-side until checked --
        // see random-impact-toggle.ts's single body-class toggle.
        $i->dontSeeElement(SearchResultsPage::SELECTOR_BADGE);

        $i->click(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX);
        $i->wait(1);
        $i->seeElement(SearchResultsPage::SELECTOR_BADGE);

        // Toggling back off hides them again -- the same body-class flip in reverse.
        $i->click(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX);
        $i->wait(1);
        $i->dontSeeElement(SearchResultsPage::SELECTOR_BADGE);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester $i
     */
    public function everyVisibleBadgeIsEitherPositiveOrNegative(SearchRankingWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX, 10);

        $i->click(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX);
        $i->wait(1);
        $i->seeElement(SearchResultsPage::SELECTOR_BADGE);

        // Every rendered badge carries exactly one of the two sign/color modifier classes -- there is no
        // third, neutral state (a product whose position wouldn't change is simply never rendered at all,
        // see RandomImpactCalculatorInterface::calculate()).
        $totalCount = $i->executeJS('return document.querySelectorAll(arguments[0]).length;', [SearchResultsPage::SELECTOR_BADGE]);
        $signedCount = $i->executeJS(
            'return document.querySelectorAll(arguments[0]).length;',
            [SearchResultsPage::SELECTOR_BADGE_POSITIVE . ', ' . SearchResultsPage::SELECTOR_BADGE_NEGATIVE],
        );
        $i->assertGreaterThan(0, $totalCount);
        $i->assertEquals($totalCount, $signedCount);
    }

    /**
     * The badge is absolutely positioned inside the same wrapper search-debug's overlay anchors to, and
     * that wrapper already produced one real height-collapse bug in this demoshop. The sibling
     * spryker-community/search-ranking-optimizer package asserts the same coexistence for its own rating
     * widget; this package shares the tile with both and had no equivalent assertion.
     *
     * @param \SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester $i
     */
    public function coexistsWithTheSearchDebugOverlayOnTheSameTile(SearchRankingWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR_WITH_SEARCH_DEBUG);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX, 10);
        $i->seeElement(SearchResultsPage::SELECTOR_SEARCH_DEBUG_TRIGGER);

        $i->click(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX);
        $i->wait(1);

        // Both render fully on the same tile — neither collapses or hides the other.
        $i->seeElement(SearchResultsPage::SELECTOR_BADGE);
        $i->seeElement(SearchResultsPage::SELECTOR_SEARCH_DEBUG_TRIGGER);
    }
}
