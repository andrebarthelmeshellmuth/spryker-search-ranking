<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject;

/**
 * Shared by both the Create and Edit forms - MetricForm::getBlockPrefix() is "search_ranking_metric"
 * on both, confirmed live against this demoshop.
 */
class MetricFormPage
{
    /**
     * @var string
     */
    public const URL_CREATE = '/search-ranking-gui/create';

    /**
     * @var string
     */
    public const URL_EDIT = '/search-ranking-gui/edit';

    /**
     * @var string
     */
    public const FIELD_NAME = 'search_ranking_metric_name';

    /**
     * @var string
     */
    public const FIELD_WEIGHT = 'search_ranking_metric_weight';

    /**
     * @var string
     */
    public const FIELD_IS_HIGHER_BETTER = 'search_ranking_metric_isHigherBetter';

    /**
     * @var string
     */
    public const FIELD_FORMULA = 'search_ranking_metric_formula';

    /**
     * @var string
     */
    public const FIELD_IS_ACTIVE = 'search_ranking_metric_isActive';

    /**
     * @var string
     */
    public const SELECTOR_SUBMIT = 'button[type="submit"]';

    /**
     * @var string
     */
    public const SELECTOR_NORMALIZATION_PREVIEW = '.search-ranking-normalization-preview';

    /**
     * @var string
     */
    public const SELECTOR_PREVIEW_MESSAGE = '.search-ranking-normalization-preview [data-role="message"]';

    /**
     * @var string
     */
    public const SELECTOR_PREVIEW_PLOT = '.search-ranking-normalization-preview [data-role="plot"]';

    /**
     * @var string
     */
    public const SELECTOR_PREVIEW_CANDIDATES_ROW = '.search-ranking-normalization-preview [data-role="candidates-body"] tr';

    /**
     * @var string
     */
    public const FLASH_MESSAGE_CREATED_FORMAT = 'Metric "%s" was created.';

    /**
     * @var string
     */
    public const FLASH_MESSAGE_UPDATED_FORMAT = 'Metric "%s" was updated.';
}
