<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject;

class ProductMetricPage
{
    /**
     * @var string
     */
    public const URL = '/search-ranking-gui/product-metric';

    /**
     * @var string
     */
    public const URL_GAP = '/search-ranking-gui/product-metric-gap';

    /**
     * @var string
     */
    public const SELECTOR_TABLE = '.dataTable';

    /**
     * @var string
     */
    public const SELECTOR_METRIC_SELECT = '#metric';

    /**
     * @var string
     */
    public const VIEW_GAPS_LINK_TEXT = 'View Gaps';
}
