<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingCalibration;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingCalibrationSearchTerm;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingPersistenceFactory getFactory()
 */
class SearchRankingEntityManager extends AbstractEntityManager implements SearchRankingEntityManagerInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer
    {
        $metricEntity = null;

        if ($metricTransfer->getIdSearchRankingMetric() !== null) {
            $metricEntity = $this->getFactory()
                ->createSearchRankingMetricQuery()
                ->findOneByIdSearchRankingMetric($metricTransfer->getIdSearchRankingMetric());
        }

        if ($metricEntity === null) {
            $metricEntity = new SpySearchRankingMetric();
        }

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $metricEntity = $mapper->mapMetricTransferToEntity($metricTransfer, $metricEntity);
        $metricEntity->save();

        return $mapper->mapMetricEntityToTransfer($metricEntity, $metricTransfer);
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return void
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $metricEntity = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->findOneByIdSearchRankingMetric($idSearchRankingMetric);

        if ($metricEntity === null) {
            return;
        }

        $metricEntity->delete();
    }

    /**
     * @param array<int, float> $normalizedValuesByIdProductMetric
     *
     * @return void
     */
    public function updateNormalizedValues(array $normalizedValuesByIdProductMetric): void
    {
        if ($normalizedValuesByIdProductMetric === []) {
            return;
        }

        $productMetricEntities = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByIdSearchRankingProductMetric_In(array_keys($normalizedValuesByIdProductMetric))
            ->find();

        foreach ($productMetricEntities as $productMetricEntity) {
            $productMetricEntity->setNormalizedValue(
                $normalizedValuesByIdProductMetric[$productMetricEntity->getIdSearchRankingProductMetric()],
            );
            $productMetricEntity->save();
        }
    }

    /**
     * Updates only the `weight` column directly — deliberately bypasses `saveMetric()`'s mandatory
     * formula re-validation (see `MetricWriter::saveMetric()`), which is unnecessary work and an
     * unnecessary failure surface here since the formula itself never changes. Same "narrow, single-field
     * write" shape as {@see updateNormalizedValues()} above.
     *
     * @param array<int, float> $weightsByIdSearchRankingMetric
     *
     * @return void
     */
    public function updateMetricWeights(array $weightsByIdSearchRankingMetric): void
    {
        if ($weightsByIdSearchRankingMetric === []) {
            return;
        }

        $metricEntities = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->filterByIdSearchRankingMetric_In(array_keys($weightsByIdSearchRankingMetric))
            ->find();

        foreach ($metricEntities as $metricEntity) {
            $metricEntity->setWeight($weightsByIdSearchRankingMetric[$metricEntity->getIdSearchRankingMetric()]);
            $metricEntity->save();
        }
    }

    /**
     * @param string $settingKey
     * @param string $settingValue
     *
     * @return void
     */
    public function saveSetting(string $settingKey, string $settingValue): void
    {
        $settingEntity = $this->getFactory()
            ->createSearchRankingSettingQuery()
            ->filterBySettingKey($settingKey)
            ->findOneOrCreate();

        $settingEntity->setSettingValue($settingValue);
        $settingEntity->save();
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingCalibrationTransfer $calibrationTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer
     */
    public function createCalibration(SearchRankingCalibrationTransfer $calibrationTransfer): SearchRankingCalibrationTransfer
    {
        $calibrationEntity = new SpySearchRankingCalibration();
        $calibrationEntity->setRelevantProductCount($calibrationTransfer->getRelevantProductCountOrFail());
        $calibrationEntity->setStoreName($calibrationTransfer->getStoreNameOrFail());
        $calibrationEntity->setLocaleName($calibrationTransfer->getLocaleNameOrFail());
        $calibrationEntity->setStatus($calibrationTransfer->getStatus() ?? SearchRankingConfig::CALIBRATION_STATUS_UPLOADED);
        $calibrationEntity->save();

        foreach ($calibrationTransfer->getSearchTerms() as $searchTermTransfer) {
            $searchTermEntity = new SpySearchRankingCalibrationSearchTerm();
            $searchTermEntity->setFkSearchRankingCalibration($calibrationEntity->getIdSearchRankingCalibration());
            $searchTermEntity->setSearchTerm($searchTermTransfer->getSearchTermOrFail());
            $searchTermEntity->save();

            $searchTermTransfer
                ->setIdSearchRankingCalibrationSearchTerm($searchTermEntity->getIdSearchRankingCalibrationSearchTerm())
                ->setFkSearchRankingCalibration($calibrationEntity->getIdSearchRankingCalibration());
        }

        return $this->getFactory()
            ->createSearchRankingMapper()
            ->mapCalibrationEntityToTransfer($calibrationEntity, $calibrationTransfer);
    }

    /**
     * @param int $idSearchRankingCalibration
     * @param string $status
     *
     * @return void
     */
    public function updateCalibrationStatus(int $idSearchRankingCalibration, string $status): void
    {
        $calibrationEntity = $this->getFactory()
            ->createSearchRankingCalibrationQuery()
            ->findOneByIdSearchRankingCalibration($idSearchRankingCalibration);

        if ($calibrationEntity === null) {
            return;
        }

        $calibrationEntity->setStatus($status);
        $calibrationEntity->save();
    }

    /**
     * @param int $idSearchRankingCalibrationSearchTerm
     * @param int $productsFound
     * @param array<float> $scores
     *
     * @return void
     */
    public function saveCalibrationSearchTermResult(int $idSearchRankingCalibrationSearchTerm, int $productsFound, array $scores): void
    {
        $searchTermEntity = $this->getFactory()
            ->createSearchRankingCalibrationSearchTermQuery()
            ->findOneByIdSearchRankingCalibrationSearchTerm($idSearchRankingCalibrationSearchTerm);

        if ($searchTermEntity === null) {
            return;
        }

        $searchTermEntity->setProductsFound($productsFound);
        $searchTermEntity->setScores($this->getFactory()->createSearchRankingMapper()->implodeScores($scores));
        $searchTermEntity->save();
    }

    /**
     * @param int $idSearchRankingCalibration
     * @param \Generated\Shared\Transfer\SearchRankingCalibrationTransfer $statisticsTransfer
     *
     * @return void
     */
    public function saveCalibrationStatistics(int $idSearchRankingCalibration, SearchRankingCalibrationTransfer $statisticsTransfer): void
    {
        $calibrationEntity = $this->getFactory()
            ->createSearchRankingCalibrationQuery()
            ->findOneByIdSearchRankingCalibration($idSearchRankingCalibration);

        if ($calibrationEntity === null) {
            return;
        }

        $calibrationEntity->setComputedK($statisticsTransfer->getComputedK());
        $calibrationEntity->setScoreMin($statisticsTransfer->getScoreMin());
        $calibrationEntity->setScoreMax($statisticsTransfer->getScoreMax());
        $calibrationEntity->setScoreMean($statisticsTransfer->getScoreMean());
        $calibrationEntity->setScoreMedian($statisticsTransfer->getScoreMedian());
        $calibrationEntity->setScoreP25($statisticsTransfer->getScoreP25());
        $calibrationEntity->setScoreP75($statisticsTransfer->getScoreP75());
        $calibrationEntity->setSampleCount($statisticsTransfer->getSampleCount());
        $calibrationEntity->setCalculatedAt(date('c'));
        $calibrationEntity->setStatus(SearchRankingConfig::CALIBRATION_STATUS_CALCULATED);
        $calibrationEntity->save();
    }

    /**
     * @param int $idSearchRankingCalibration
     * @param string $errorMessage
     *
     * @return void
     */
    public function markCalibrationFailed(int $idSearchRankingCalibration, string $errorMessage): void
    {
        $calibrationEntity = $this->getFactory()
            ->createSearchRankingCalibrationQuery()
            ->findOneByIdSearchRankingCalibration($idSearchRankingCalibration);

        if ($calibrationEntity === null) {
            return;
        }

        $calibrationEntity->setStatus(SearchRankingConfig::CALIBRATION_STATUS_FAILED);
        $calibrationEntity->setErrorMessage($errorMessage);
        $calibrationEntity->save();
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer
     */
    public function saveMetricDigest(SearchRankingMetricDigestTransfer $digestTransfer): SearchRankingMetricDigestTransfer
    {
        $digestEntity = $this->getFactory()
            ->createSearchRankingMetricDigestQuery()
            ->filterByFkSearchRankingMetric($digestTransfer->getFkSearchRankingMetricOrFail())
            ->findOneOrCreate();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $digestEntity = $mapper->mapMetricDigestTransferToEntity($digestTransfer, $digestEntity);
        $digestEntity->save();

        return $mapper->mapMetricDigestEntityToTransfer($digestEntity, $digestTransfer);
    }

    /**
     * Always inserts a new row — history is append-only, never updated or upserted, unlike every other
     * write in this class.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer $historyTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer
     */
    public function recordMetricHistory(SearchRankingMetricHistoryTransfer $historyTransfer): SearchRankingMetricHistoryTransfer
    {
        $historyEntity = new SpySearchRankingMetricHistory();

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $historyEntity = $mapper->mapMetricHistoryTransferToEntity($historyTransfer, $historyEntity);
        $historyEntity->save();

        return $mapper->mapMetricHistoryEntityToTransfer($historyEntity, $historyTransfer);
    }
}
