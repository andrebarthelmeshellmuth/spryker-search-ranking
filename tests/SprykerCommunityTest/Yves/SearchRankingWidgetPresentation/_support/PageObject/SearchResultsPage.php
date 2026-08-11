<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchRankingWidgetPresentation\PageObject;

class SearchResultsPage
{
    /**
     * ~56 hits in this demoshop's catalog — the reliable baseline query, same one the sibling
     * search-debug/search-ranking-optimizer suites use.
     *
     * @var string
     */
    public const URL_CHAIR = '/en/search?q=chair';

    /**
     * Same query, with search-debug's own overlay also active — the badge shares a product tile (and a
     * wrapper) with it, so coexistence needs its own assertion.
     *
     * @var string
     */
    public const URL_CHAIR_WITH_SEARCH_DEBUG = '/en/search?q=chair&searchDebugInfo=1';

    /**
     * search-debug's own per-product score trigger, asserted alongside this package's badge.
     *
     * @var string
     */
    public const SELECTOR_SEARCH_DEBUG_TRIGGER = '.search-debug-trigger';

    /**
     * @var string
     */
    public const SELECTOR_TOGGLE_CHECKBOX = '.js-random-impact-toggle__checkbox';

    /**
     * @var string
     */
    public const SELECTOR_TOGGLE_DISCLAIMER = '.random-impact-toggle__disclaimer';

    /**
     * @var string
     */
    public const SELECTOR_BADGE = '.random-impact-badge';

    /**
     * @var string
     */
    public const SELECTOR_BADGE_POSITIVE = '.random-impact-badge--positive';

    /**
     * @var string
     */
    public const SELECTOR_BADGE_NEGATIVE = '.random-impact-badge--negative';
}
