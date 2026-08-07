<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence\Propel\Mapper;

use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingMetricWeightTransfer;
use Generated\Shared\Transfer\SearchRankingProductMetricTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer;
use Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfig;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricWeight;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingScopeCopyLock;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingSettingHistory;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

class SearchRankingMapper
{
    /**
     * formula/isActive/shape are NOT read from the entity here — `spy_search_ranking_metric` itself only
     * carries id/name/isHigherBetter/isLocaleScoped any more; formula/isActive/shape moved to
     * `spy_search_ranking_metric_store_config`, store-scoped (a breaking change; see CHANGELOG.md for the
     * release that shipped it — no live installs meant no backfill migration was needed). This method
     * only maps what's still genuinely global. Callers that need formula/isActive/shape overlay them via
     * {@see SearchRankingRepository::attachStoreConfig()}, the same composable-overlay shape
     * {@see SearchRankingRepository::attachWeight()} already established.
     *
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     */
    public function mapMetricEntityToTransfer(
        SpySearchRankingMetric $metricEntity,
        SearchRankingMetricTransfer $metricTransfer,
    ): SearchRankingMetricTransfer {
        return $metricTransfer
            ->setIdSearchRankingMetric($metricEntity->getIdSearchRankingMetric())
            ->setName($metricEntity->getName())
            ->setIsHigherBetter($metricEntity->getIsHigherBetter())
            ->setIsLocaleScoped($metricEntity->getIsLocaleScoped());
    }

    /**
     * Only writes name/isHigherBetter/isLocaleScoped — formula/isActive/shape are saved separately, to
     * `spy_search_ranking_metric_store_config`, by
     * {@see SearchRankingEntityManager::saveMetricStoreConfig()}.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric $metricEntity
     */
    public function mapMetricTransferToEntity(
        SearchRankingMetricTransfer $metricTransfer,
        SpySearchRankingMetric $metricEntity,
    ): SpySearchRankingMetric {
        $metricEntity->setName($metricTransfer->getNameOrFail());
        $metricEntity->setIsHigherBetter($metricTransfer->getIsHigherBetter() ?? true);
        $metricEntity->setIsLocaleScoped($metricTransfer->getIsLocaleScoped() ?? true);

        return $metricEntity;
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricWeight $metricWeightEntity
     * @param \Generated\Shared\Transfer\SearchRankingMetricWeightTransfer $metricWeightTransfer
     */
    public function mapMetricWeightEntityToTransfer(
        SpySearchRankingMetricWeight $metricWeightEntity,
        SearchRankingMetricWeightTransfer $metricWeightTransfer,
    ): SearchRankingMetricWeightTransfer {
        return $metricWeightTransfer
            ->setIdSearchRankingMetricWeight($metricWeightEntity->getIdSearchRankingMetricWeight())
            ->setFkSearchRankingMetric($metricWeightEntity->getFkSearchRankingMetric())
            ->setStoreName($metricWeightEntity->getStoreName())
            ->setLocaleName($metricWeightEntity->getLocaleName())
            ->setWeight($metricWeightEntity->getWeight());
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfig $metricStoreConfigEntity
     * @param \Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer $metricStoreConfigTransfer
     */
    public function mapMetricStoreConfigEntityToTransfer(
        SpySearchRankingMetricStoreConfig $metricStoreConfigEntity,
        SearchRankingMetricStoreConfigTransfer $metricStoreConfigTransfer,
    ): SearchRankingMetricStoreConfigTransfer {
        return $metricStoreConfigTransfer
            ->setIdSearchRankingMetricStoreConfig($metricStoreConfigEntity->getIdSearchRankingMetricStoreConfig())
            ->setFkSearchRankingMetric($metricStoreConfigEntity->getFkSearchRankingMetric())
            ->setStoreName($metricStoreConfigEntity->getStoreName())
            ->setFormula($metricStoreConfigEntity->getFormula())
            ->setIsActive($metricStoreConfigEntity->getIsActive())
            ->setShape($metricStoreConfigEntity->getShape());
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricStoreConfigTransfer $metricStoreConfigTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfig $metricStoreConfigEntity
     */
    public function mapMetricStoreConfigTransferToEntity(
        SearchRankingMetricStoreConfigTransfer $metricStoreConfigTransfer,
        SpySearchRankingMetricStoreConfig $metricStoreConfigEntity,
    ): SpySearchRankingMetricStoreConfig {
        $metricStoreConfigEntity->setFkSearchRankingMetric($metricStoreConfigTransfer->getFkSearchRankingMetricOrFail());
        $metricStoreConfigEntity->setStoreName($metricStoreConfigTransfer->getStoreNameOrFail());
        $metricStoreConfigEntity->setFormula($metricStoreConfigTransfer->getFormulaOrFail());
        $metricStoreConfigEntity->setIsActive($metricStoreConfigTransfer->getIsActive() ?? true);
        $metricStoreConfigEntity->setShape($metricStoreConfigTransfer->getShape());

        return $metricStoreConfigEntity;
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetric $productMetricEntity
     * @param \Generated\Shared\Transfer\SearchRankingProductMetricTransfer $productMetricTransfer
     */
    public function mapProductMetricEntityToTransfer(
        SpySearchRankingProductMetric $productMetricEntity,
        SearchRankingProductMetricTransfer $productMetricTransfer,
    ): SearchRankingProductMetricTransfer {
        return $productMetricTransfer
            ->setIdSearchRankingProductMetric($productMetricEntity->getIdSearchRankingProductMetric())
            ->setFkSearchRankingMetric($productMetricEntity->getFkSearchRankingMetric())
            ->setFkProductAbstract($productMetricEntity->getFkProductAbstract())
            ->setStoreName($productMetricEntity->getStoreName())
            ->setLocaleName($productMetricEntity->getLocaleName())
            ->setRawValue($productMetricEntity->getRawValue())
            ->setNormalizedValue($productMetricEntity->getNormalizedValue());
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest $digestEntity
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     */
    public function mapMetricDigestEntityToTransfer(
        SpySearchRankingMetricDigest $digestEntity,
        SearchRankingMetricDigestTransfer $digestTransfer,
    ): SearchRankingMetricDigestTransfer {
        return $digestTransfer
            ->setIdSearchRankingMetricDigest($digestEntity->getIdSearchRankingMetricDigest())
            ->setFkSearchRankingMetric($digestEntity->getFkSearchRankingMetric())
            ->setStoreName($digestEntity->getStoreName())
            ->setLocaleName($digestEntity->getLocaleName())
            ->setMinValue($digestEntity->getMinValue())
            ->setMaxValue($digestEntity->getMaxValue())
            ->setMeanValue($digestEntity->getMeanValue())
            ->setMedianValue($digestEntity->getMedianValue())
            ->setSampleCount($digestEntity->getSampleCount())
            ->setPercentiles($this->explodePercentiles($digestEntity->getPercentiles()));
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigest $digestEntity
     */
    public function mapMetricDigestTransferToEntity(
        SearchRankingMetricDigestTransfer $digestTransfer,
        SpySearchRankingMetricDigest $digestEntity,
    ): SpySearchRankingMetricDigest {
        $digestEntity->setFkSearchRankingMetric($digestTransfer->getFkSearchRankingMetricOrFail());
        $digestEntity->setStoreName($digestTransfer->getStoreNameOrFail());
        $digestEntity->setLocaleName($digestTransfer->getLocaleNameOrFail());
        $digestEntity->setMinValue($digestTransfer->getMinValueOrFail());
        $digestEntity->setMaxValue($digestTransfer->getMaxValueOrFail());
        $digestEntity->setMeanValue($digestTransfer->getMeanValueOrFail());
        $digestEntity->setMedianValue($digestTransfer->getMedianValueOrFail());
        $digestEntity->setSampleCount($digestTransfer->getSampleCountOrFail());
        $digestEntity->setPercentiles($this->implodePercentiles($digestTransfer->getPercentiles()));

        return $digestEntity;
    }

    /**
     * @param string|null $percentiles
     *
     * @return array<float>
     */
    protected function explodePercentiles(?string $percentiles): array
    {
        if ($percentiles === null || $percentiles === '') {
            return [];
        }

        return array_map(static fn ($value): float => (float)$value, explode(',', $percentiles));
    }

    /**
     * @param array<float> $percentiles
     */
    protected function implodePercentiles(array $percentiles): string
    {
        return implode(',', $percentiles);
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory $historyEntity
     * @param \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer $historyTransfer
     */
    public function mapMetricHistoryEntityToTransfer(
        SpySearchRankingMetricHistory $historyEntity,
        SearchRankingMetricHistoryTransfer $historyTransfer,
    ): SearchRankingMetricHistoryTransfer {
        return $historyTransfer
            ->setIdSearchRankingMetricHistory($historyEntity->getIdSearchRankingMetricHistory())
            ->setFkSearchRankingMetric($historyEntity->getFkSearchRankingMetric())
            ->setMetricName($historyEntity->getMetricName())
            ->setStoreName($historyEntity->getStoreName())
            ->setLocaleName($historyEntity->getLocaleName())
            ->setWeight($historyEntity->getWeight())
            ->setFormula($historyEntity->getFormula())
            ->setIsActive($historyEntity->getIsActive())
            ->setIsHigherBetter($historyEntity->getIsHigherBetter())
            ->setIsLocaleScoped($historyEntity->getIsLocaleScoped())
            ->setMinValue($historyEntity->getMinValue())
            ->setMaxValue($historyEntity->getMaxValue())
            ->setMeanValue($historyEntity->getMeanValue())
            ->setMedianValue($historyEntity->getMedianValue())
            ->setSampleCount($historyEntity->getSampleCount())
            ->setPercentiles($this->explodePercentiles($historyEntity->getPercentiles()))
            ->setFitRSquared($historyEntity->getFitRSquared())
            ->setIsChange($historyEntity->getIsChange())
            ->setChangeSource($historyEntity->getChangeSource())
            ->setCreatedAt($historyEntity->getCreatedAt()?->format(DATE_ATOM));
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer $historyTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistory $historyEntity
     */
    public function mapMetricHistoryTransferToEntity(
        SearchRankingMetricHistoryTransfer $historyTransfer,
        SpySearchRankingMetricHistory $historyEntity,
    ): SpySearchRankingMetricHistory {
        $historyEntity->setFkSearchRankingMetric($historyTransfer->getFkSearchRankingMetricOrFail());
        $historyEntity->setMetricName($historyTransfer->getMetricNameOrFail());
        $historyEntity->setStoreName($historyTransfer->getStoreNameOrFail());
        $historyEntity->setLocaleName($historyTransfer->getLocaleNameOrFail());
        $historyEntity->setWeight($historyTransfer->getWeightOrFail());
        $historyEntity->setFormula($historyTransfer->getFormulaOrFail());
        $historyEntity->setIsActive($historyTransfer->getIsActiveOrFail());
        $historyEntity->setIsHigherBetter($historyTransfer->getIsHigherBetterOrFail());
        $historyEntity->setIsLocaleScoped($historyTransfer->getIsLocaleScopedOrFail());
        $historyEntity->setMinValue($historyTransfer->getMinValue());
        $historyEntity->setMaxValue($historyTransfer->getMaxValue());
        $historyEntity->setMeanValue($historyTransfer->getMeanValue());
        $historyEntity->setMedianValue($historyTransfer->getMedianValue());
        $historyEntity->setSampleCount($historyTransfer->getSampleCount());
        $historyEntity->setPercentiles(
            $historyTransfer->getPercentiles() === [] ? null : $this->implodePercentiles($historyTransfer->getPercentiles()),
        );
        $historyEntity->setFitRSquared($historyTransfer->getFitRSquared());
        $historyEntity->setIsChange($historyTransfer->getIsChange() ?? true);
        $historyEntity->setChangeSource($historyTransfer->getChangeSource() ?? SharedSearchRankingConfig::CHANGE_SOURCE_MANUAL);

        return $historyEntity;
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingSettingHistory $settingHistoryEntity
     * @param \Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer $settingHistoryTransfer
     */
    public function mapSettingHistoryEntityToTransfer(
        SpySearchRankingSettingHistory $settingHistoryEntity,
        SearchRankingSettingHistoryTransfer $settingHistoryTransfer,
    ): SearchRankingSettingHistoryTransfer {
        return $settingHistoryTransfer
            ->setIdSearchRankingSettingHistory($settingHistoryEntity->getIdSearchRankingSettingHistory())
            ->setSettingKey($settingHistoryEntity->getSettingKey())
            ->setStoreName($settingHistoryEntity->getStoreName())
            ->setLocaleName($settingHistoryEntity->getLocaleName())
            ->setSettingValue($settingHistoryEntity->getSettingValue())
            ->setCreatedAt($settingHistoryEntity->getCreatedAt()?->format(DATE_ATOM));
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer $settingHistoryTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingSettingHistory $settingHistoryEntity
     */
    public function mapSettingHistoryTransferToEntity(
        SearchRankingSettingHistoryTransfer $settingHistoryTransfer,
        SpySearchRankingSettingHistory $settingHistoryEntity,
    ): SpySearchRankingSettingHistory {
        $settingHistoryEntity->setSettingKey($settingHistoryTransfer->getSettingKeyOrFail());
        $settingHistoryEntity->setStoreName($settingHistoryTransfer->getStoreNameOrFail());
        $settingHistoryEntity->setLocaleName($settingHistoryTransfer->getLocaleNameOrFail());
        $settingHistoryEntity->setSettingValue($settingHistoryTransfer->getSettingValueOrFail());

        return $settingHistoryEntity;
    }

    /**
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingScopeCopyLock $scopeCopyLockEntity
     * @param \Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer $scopeCopyLockTransfer
     */
    public function mapScopeCopyLockEntityToTransfer(
        SpySearchRankingScopeCopyLock $scopeCopyLockEntity,
        SearchRankingScopeCopyLockTransfer $scopeCopyLockTransfer,
    ): SearchRankingScopeCopyLockTransfer {
        return $scopeCopyLockTransfer
            ->setIdSearchRankingScopeCopyLock($scopeCopyLockEntity->getIdSearchRankingScopeCopyLock())
            ->setSourceStoreName($scopeCopyLockEntity->getSourceStoreName())
            ->setSourceLocaleName($scopeCopyLockEntity->getSourceLocaleName())
            ->setTargetStoreName($scopeCopyLockEntity->getTargetStoreName())
            ->setTargetLocaleName($scopeCopyLockEntity->getTargetLocaleName())
            ->setIsActive($scopeCopyLockEntity->getIsActive())
            ->setCreatedAt($scopeCopyLockEntity->getCreatedAt()?->format(DATE_ATOM))
            ->setDeactivatedAt($scopeCopyLockEntity->getDeactivatedAt()?->format(DATE_ATOM));
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer $scopeCopyLockTransfer
     * @param \Orm\Zed\SearchRanking\Persistence\SpySearchRankingScopeCopyLock $scopeCopyLockEntity
     */
    public function mapScopeCopyLockTransferToEntity(
        SearchRankingScopeCopyLockTransfer $scopeCopyLockTransfer,
        SpySearchRankingScopeCopyLock $scopeCopyLockEntity,
    ): SpySearchRankingScopeCopyLock {
        $scopeCopyLockEntity->setSourceStoreName($scopeCopyLockTransfer->getSourceStoreNameOrFail());
        $scopeCopyLockEntity->setSourceLocaleName($scopeCopyLockTransfer->getSourceLocaleNameOrFail());
        $scopeCopyLockEntity->setTargetStoreName($scopeCopyLockTransfer->getTargetStoreNameOrFail());
        $scopeCopyLockEntity->setTargetLocaleName($scopeCopyLockTransfer->getTargetLocaleNameOrFail());
        $scopeCopyLockEntity->setIsActive($scopeCopyLockTransfer->getIsActive() ?? true);

        return $scopeCopyLockEntity;
    }
}
