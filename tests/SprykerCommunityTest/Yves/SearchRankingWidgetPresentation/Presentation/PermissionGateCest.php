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
 * The "Show random impact" toggle and every badge are gated behind
 * SeeSearchRankingRandomImpactPermissionPlugin — RandomImpactResultFormatterPlugin never even computes
 * deltas for a visitor without it, so the whole toggle molecule is absent from the DOM, not merely hidden.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchRankingWidgetPresentation
 * @group Presentation
 * @group PermissionGateCest
 * Add your own group annotations below this line
 */
class PermissionGateCest
{
    /**
     * @param \SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester $i
     */
    public function _before(SearchRankingWidgetPresentationTester $i): void
    {
        $i->amYves();
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester $i
     */
    public function anonymousShopperSeesNoToggle(SearchRankingWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->dontSeeElement(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX);
        $i->dontSeeElement(SearchResultsPage::SELECTOR_BADGE);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester $i
     */
    public function loggedInCustomerWithoutTheRoleSeesNoToggle(SearchRankingWidgetPresentationTester $i): void
    {
        $i->loginAsCustomer(SearchRankingWidgetPresentationTester::UNPERMITTED_CUSTOMER_EMAIL);
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->dontSeeElement(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\SearchRankingWidgetPresentationTester $i
     */
    public function permittedCustomerSeesTheToggleAndDisclaimer(SearchRankingWidgetPresentationTester $i): void
    {
        // Positive control for the two negative tests above.
        $i->loginAsCustomer(SearchRankingWidgetPresentationTester::PERMITTED_CUSTOMER_EMAIL);
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_TOGGLE_CHECKBOX, 10);
        $i->seeElement(SearchResultsPage::SELECTOR_TOGGLE_DISCLAIMER);
    }
}
