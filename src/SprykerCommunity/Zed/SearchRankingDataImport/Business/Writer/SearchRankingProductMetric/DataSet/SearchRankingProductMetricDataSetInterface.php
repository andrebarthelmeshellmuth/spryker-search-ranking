<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet;

interface SearchRankingProductMetricDataSetInterface
{
    /**
     * @var string
     */
    public const COL_ABSTRACT_SKU = 'abstract_sku';

    /**
     * @var string
     */
    public const COL_METRIC_NAME = 'metric_name';

    /**
     * @var string
     */
    public const COL_RAW_VALUE = 'raw_value';

    /**
     * @var string
     */
    public const COL_STORE = 'store';

    /**
     * A single locale (e.g. `de_DE`) or a comma-separated list of locales (e.g. `de_DE,en_US`) — a
     * metric that doesn't genuinely vary by locale (a store-wide fact like sales/stock) can list every
     * locale it applies to in one row instead of one row per locale;
     * {@see \SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\SearchRankingProductMetricWriterStep}
     * writes the same raw value into each listed locale.
     *
     * @var string
     */
    public const COL_LOCALE = 'locale';

    /**
     * @var string
     */
    public const KEY_ID_SEARCH_RANKING_METRIC = 'id_search_ranking_metric';

    /**
     * @var string
     */
    public const KEY_ID_PRODUCT_ABSTRACT = 'id_product_abstract';
}
