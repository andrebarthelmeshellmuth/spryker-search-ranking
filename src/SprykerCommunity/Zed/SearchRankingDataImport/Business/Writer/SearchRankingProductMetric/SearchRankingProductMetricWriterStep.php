<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric;

use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\DataImport\Business\Exception\InvalidDataException;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;

/**
 * Writes directly to Propel, same DataImport convention as SearchRankingMetricWriterStep — but unlike
 * that step, there's no facade-level "save one product metric" method being bypassed here: this direct
 * upsert is the only path this data ever takes, from any source. Only rawValue is set, deliberately;
 * normalizedValue is left untouched because normalization always happens downstream, in
 * ProductMetricNormalizer via the search-ranking:normalize cron — never at import time.
 */
class SearchRankingProductMetricWriterStep implements DataImportStepInterface
{
    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @throws \Spryker\Zed\DataImport\Business\Exception\InvalidDataException
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $rawValue = $dataSet[SearchRankingProductMetricDataSetInterface::COL_RAW_VALUE];

        // A blank cell, stray whitespace, or a non-numeric string (e.g. "N/A", or "1,196" with a
        // thousands separator) casts silently to 0.0 under a plain (float) cast -- indistinguishable
        // from a legitimately-zero signal. Fail the row instead, same as the sibling metric writer does
        // for a malformed name.
        if (!is_numeric($rawValue)) {
            throw new InvalidDataException(
                sprintf(
                    'Failed to import search ranking product metric for product abstract "%s": raw value "%s" is not numeric.',
                    $dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT],
                    $rawValue,
                ),
            );
        }

        foreach ($this->splitLocaleNames((string)$dataSet[SearchRankingProductMetricDataSetInterface::COL_LOCALE]) as $localeName) {
            $productMetricEntity = SpySearchRankingProductMetricQuery::create()
                ->filterByFkSearchRankingMetric($dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_SEARCH_RANKING_METRIC])
                ->filterByFkProductAbstract($dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT])
                ->filterByStoreName((string)$dataSet[SearchRankingProductMetricDataSetInterface::COL_STORE])
                ->filterByLocaleName($localeName)
                ->findOneOrCreate();

            $productMetricEntity->setRawValue((float)$rawValue);

            $productMetricEntity->save();
        }
    }

    /**
     * A single locale (`de_DE`) or a comma-separated list (`de_DE,en_US`) — see
     * {@see \SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface::COL_LOCALE}.
     * Entries are trimmed so `de_DE, en_US` (with incidental whitespace after the comma) works the same
     * as `de_DE,en_US`.
     *
     * @param string $localeNamesColumnValue
     *
     * @return array<string>
     */
    protected function splitLocaleNames(string $localeNamesColumnValue): array
    {
        return array_map(trim(...), explode(',', $localeNamesColumnValue));
    }
}
