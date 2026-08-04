<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric;

use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricWeightQuery;
use Spryker\Zed\DataImport\Business\Exception\InvalidDataException;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\DataSet\SearchRankingMetricDataSetInterface;

/**
 * Writes directly to Propel rather than through SearchRankingFacade::saveMetric() — the standard
 * Spryker DataImport convention (see e.g. core's CountryDataImport\...\CountryStoreWriterStep), needed
 * because the batch/transaction machinery around this step already handles chunking, so a per-row
 * facade call would add transfer-mapping and transaction overhead on top of that for no benefit. The
 * real cost: this skips the formula validation and metric-history recording saveMetric() does, so a
 * malformed formula in a CSV row is only caught later — as a per-metric skip reported by the next
 * search-ranking:normalize run, not immediately at import time.
 *
 * The metric NAME is validated here, though, and fails the row immediately rather than deferring: unlike
 * a bad formula (skipped per-metric at normalize time, safe either way), a name that doesn't match
 * {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder::METRIC_NAME_PATTERN} would
 * silently never contribute to the live `function_score` query — `FunctionScoreBuilder` skips names it
 * doesn't recognize as safe painless-script identifiers — while still consuming weight-normalization
 * budget and still rendering as a live contribution in the search-debug overlay, which has no such
 * filter. Both symptoms are silent, so this must fail loudly instead of persisting a metric that can
 * only ever be a live-vs-debug disagreement.
 */
class SearchRankingMetricWriterStep implements DataImportStepInterface
{
    /**
     * Mirrors {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder::METRIC_NAME_PATTERN}
     * and {@see \SprykerCommunity\Zed\SearchRankingGui\Communication\Form\MetricForm}'s own `Regex`
     * constraint — duplicated, not imported, to keep this Zed module's dependencies from reaching into
     * the Client layer or a sibling Zed module for a single regex literal.
     *
     * @var string
     */
    protected const METRIC_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @throws \Spryker\Zed\DataImport\Business\Exception\InvalidDataException
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $name = (string)$dataSet[SearchRankingMetricDataSetInterface::COL_NAME];

        if (!preg_match(static::METRIC_NAME_PATTERN, $name)) {
            throw new InvalidDataException(
                sprintf(
                    'Failed to import search ranking metric "%s": name must start with a lowercase letter and contain only lowercase letters, digits and underscores.',
                    $name,
                ),
            );
        }

        $metricEntity = SpySearchRankingMetricQuery::create()
            ->filterByName($name)
            ->findOneOrCreate();

        $metricEntity
            ->setFormula((string)$dataSet[SearchRankingMetricDataSetInterface::COL_FORMULA])
            ->setIsActive((bool)$dataSet[SearchRankingMetricDataSetInterface::COL_IS_ACTIVE]);

        $metricEntity->save();

        $weight = (float)$dataSet[SearchRankingMetricDataSetInterface::COL_WEIGHT];

        // Mirrors MetricForm's GreaterThanOrEqual(0) constraint -- a negative weight here doesn't just
        // skip this one metric (like a bad formula does), it corrupts every OTHER active metric's
        // published weight too: RankingConfigurationStorageWriter::normalizeMetricWeights() divides by
        // the sum of all active weights, and a negative value can shrink that sum toward zero (or flip
        // its sign) without ever hitting the sum's own exact-zero guard, silently pushing normalized
        // weights outside [0;1] for the whole store/locale.
        if ($weight < 0) {
            throw new InvalidDataException(
                sprintf(
                    'Failed to import search ranking metric "%s": weight must be greater than or equal to 0, got "%s".',
                    $name,
                    $dataSet[SearchRankingMetricDataSetInterface::COL_WEIGHT],
                ),
            );
        }

        $metricWeightEntity = SpySearchRankingMetricWeightQuery::create()
            ->filterByFkSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
            ->filterByStoreName((string)$dataSet[SearchRankingMetricDataSetInterface::COL_STORE])
            ->filterByLocaleName((string)$dataSet[SearchRankingMetricDataSetInterface::COL_LOCALE])
            ->findOneOrCreate();

        $metricWeightEntity->setWeight($weight);
        $metricWeightEntity->save();
    }
}
