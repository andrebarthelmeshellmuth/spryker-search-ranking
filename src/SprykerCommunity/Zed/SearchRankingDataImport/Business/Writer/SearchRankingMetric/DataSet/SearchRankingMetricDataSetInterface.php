<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\DataSet;

interface SearchRankingMetricDataSetInterface
{
    /**
     * @var string
     */
    public const COL_NAME = 'name';

    /**
     * @var string
     */
    public const COL_WEIGHT = 'weight';

    /**
     * @var string
     */
    public const COL_FORMULA = 'formula';

    /**
     * @var string
     */
    public const COL_IS_ACTIVE = 'is_active';

    /**
     * @var string
     */
    public const COL_STORE = 'store';

    /**
     * @var string
     */
    public const COL_LOCALE = 'locale';
}
