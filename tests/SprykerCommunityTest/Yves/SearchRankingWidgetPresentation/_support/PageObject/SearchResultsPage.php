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
