<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject;

class SettingsPage
{
    /**
     * @var string
     */
    public const URL = '/search-ranking-gui/settings';

    /**
     * @var string
     */
    public const FIELD_RELEVANCE_WEIGHT = 'search_ranking_settings_relevanceWeight';

    /**
     * @var string
     */
    public const FIELD_RELEVANCE_SATURATION_POINT = 'search_ranking_settings_relevanceSaturationPoint';

    /**
     * @var string
     */
    public const FIELD_SPECIFICITY_BLEND_WEIGHT = 'search_ranking_settings_specificityBlendWeight';

    /**
     * @var string
     */
    public const FIELD_SPECIFICITY_SATURATION_POINT = 'search_ranking_settings_specificitySaturationPoint';

    /**
     * @var string
     */
    public const FIELD_SPECIFICITY_WEIGHT_EXPONENT = 'search_ranking_settings_specificityWeightExponent';

    /**
     * @var string
     */
    public const FIELD_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE = 'search_ranking_settings_specificityWeightShiftMagnitude';

    /**
     * @var string
     */
    public const SELECTOR_SUBMIT = 'button[type="submit"]';

    /**
     * @var string
     */
    public const FLASH_MESSAGE_SAVED = 'Ranking settings were saved.';
}
