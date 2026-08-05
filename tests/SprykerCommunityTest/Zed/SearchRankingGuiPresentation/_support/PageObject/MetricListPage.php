<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject;

class MetricListPage
{
    /**
     * @var string
     */
    public const URL = '/search-ranking-gui';

    /**
     * @var string
     */
    public const SELECTOR_TABLE = '.dataTable';

    /**
     * @var string
     */
    public const SELECTOR_STORE_SELECT = '#storeName';

    /**
     * @var string
     */
    public const SELECTOR_LOCALE_SELECT = '#localeName';

    /**
     * @var string
     */
    public const SELECTOR_EDIT_BUTTON = '.btn-edit';

    /**
     * @var string
     */
    public const SELECTOR_DELETE_BUTTON = '[data-qa="delete-button"]';

    /**
     * @var string
     */
    public const CREATE_METRIC_LINK_TEXT = 'Create Metric';

    /**
     * @var string
     */
    public const NORMALIZE_WEIGHTS_BUTTON_TEXT = 'Normalize active weights';
}
