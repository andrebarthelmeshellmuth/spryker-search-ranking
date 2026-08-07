<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject;

class MetricHistoryPage
{
    /**
     * @var string
     */
    public const URL = '/search-ranking-gui/metric-history';

    /**
     * @var string
     */
    public const SELECTOR_TABLE = '.dataTable';

    /**
     * Cross-scope audit trail — unlike every other scoped page in this GUI, unset/blank means "no
     * filter, show every store/locale" (see MetricHistoryController::resolveStoreName()'s own docblock),
     * not a fallback to DE.
     *
     * @var string
     */
    public const SELECTOR_STORE_SELECT = '#storeName';

    /**
     * @var string
     */
    public const SELECTOR_LOCALE_SELECT = '#localeName';
}
