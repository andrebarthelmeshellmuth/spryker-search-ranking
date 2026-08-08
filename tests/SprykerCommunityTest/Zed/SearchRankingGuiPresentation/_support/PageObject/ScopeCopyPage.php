<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject;

class ScopeCopyPage
{
    /**
     * @var string
     */
    public const URL = '/search-ranking-gui/scope-copy';

    /**
     * @var string
     */
    public const SELECTOR_SOURCE_STORE_SELECT = '#sourceStoreName';

    /**
     * @var string
     */
    public const SELECTOR_SOURCE_LOCALE_SELECT = '#sourceLocaleName';

    /**
     * @var string
     */
    public const SELECTOR_TARGET_STORE_SELECT = '#targetStoreName';

    /**
     * @var string
     */
    public const SELECTOR_TARGET_LOCALE_SELECT = '#targetLocaleName';

    /**
     * @var string
     */
    public const COPY_PREVIEW_HEADING_TEXT = 'This will copy:';

    /**
     * @var string
     */
    public const COPY_NOW_BUTTON_TEXT = 'Copy now';

    /**
     * @var string
     */
    public const LOCK_BUTTON_TEXT = 'Lock (sync weight/setting daily)';

    /**
     * @var string
     */
    public const SELECTOR_COPY_MODE_MIRROR = '#search_ranking_scope_copy_run_action_mode_0';

    /**
     * @var string
     */
    public const SELECTOR_COPY_MODE_COPY_ONLY_OVERLAP = '#search_ranking_scope_copy_run_action_mode_1';

    /**
     * @var string
     */
    public const SELECTOR_COPY_CONFIRM_OVERWRITE = '#search_ranking_scope_copy_run_action_confirmOverwrite';

    /**
     * @var string
     */
    public const SELECTOR_LOCK_CONFIRM_OVERWRITE = '#search_ranking_scope_copy_action_confirmOverwrite';

    /**
     * @var string
     */
    public const KEPT_IN_SYNC_BY_LOCK_HEADING_TEXT = 'Kept in sync by Lock?';
}
