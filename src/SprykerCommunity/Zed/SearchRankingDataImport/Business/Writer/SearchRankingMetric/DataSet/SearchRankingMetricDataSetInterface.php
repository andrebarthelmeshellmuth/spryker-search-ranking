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
     * A single locale (e.g. `de_DE`) or a comma-separated list of locales (e.g. `de_DE,en_US`) — a
     * metric that doesn't genuinely vary by locale (a store-wide fact like sales/stock) can list every
     * locale it applies to in one row instead of one row per locale;
     * {@see \SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\SearchRankingMetricWriterStep}
     * writes the same weight into each listed locale. Only the WEIGHT row is affected — formula/isActive
     * are store-scoped already, unrelated to this column's locale value(s).
     *
     * @var string
     */
    public const COL_LOCALE = 'locale';
}
