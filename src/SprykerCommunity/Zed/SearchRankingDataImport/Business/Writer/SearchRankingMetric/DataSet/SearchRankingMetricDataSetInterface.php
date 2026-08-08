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
     * writes the same weight AND the same formula/isActive into each listed locale (all governed by the
     * same isLocaleScoped flag, not just weight) — a real per-locale-diverging metric still needs one row
     * per locale with its own distinct formula/weight; this column is what LETS that happen, not what
     * forces every locale to match.
     *
     * @var string
     */
    public const COL_LOCALE = 'locale';
}
