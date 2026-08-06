<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

class ScopeConfigCopier implements ScopeConfigCopierInterface
{
    /**
     * @var array<string, string>
     */
    protected const SETTING_COPIERS = [
        SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT => 'saveRelevanceWeight',
        SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT => 'saveRelevanceSaturationPoint',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT => 'saveSpecificityBlendWeight',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT => 'saveSpecificitySaturationPoint',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_CURVE_EXPONENT => 'saveSpecificityCurveExponent',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT => 'saveSpecificityWeightExponent',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE => 'saveSpecificityWeightShiftMagnitude',
    ];

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface $metricWriter
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface $settingManager
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected MetricWriterInterface $metricWriter,
        protected SettingManagerInterface $settingManager,
    ) {
    }

    /**
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param bool $confirmOverwrite
     * @param string $changeSource
     */
    public function copyScopeConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        bool $confirmOverwrite,
        string $changeSource,
    ): SearchRankingScopeCopyResultTransfer {
        $resultTransfer = new SearchRankingScopeCopyResultTransfer();

        if ($sourceStoreName === $targetStoreName && $sourceLocaleName === $targetLocaleName) {
            return $resultTransfer
                ->setIsSuccess(false)
                ->setErrorMessage('Source and target scope must be different.');
        }

        if (!$confirmOverwrite && $this->hasScopeConfiguration($targetStoreName, $targetLocaleName)) {
            return $resultTransfer->setIsBlockedByExistingData(true);
        }

        $metricWeightCopiedCount = $this->copyMetricWeights(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $changeSource,
        );
        $settingCopiedCount = $this->copySettings($sourceStoreName, $sourceLocaleName, $targetStoreName, $targetLocaleName);

        return $resultTransfer
            ->setIsSuccess(true)
            ->setMetricWeightCopiedCount($metricWeightCopiedCount)
            ->setSettingCopiedCount($settingCopiedCount);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function hasScopeConfiguration(string $storeName, string $localeName): bool
    {
        return $this->repository->hasScopeConfiguration($storeName, $localeName);
    }

    /**
     * Only metrics with an explicitly-saved weight in the source scope are copied — a metric never
     * touched there stays untouched in the target too, rather than writing an explicit 0.0.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param string $changeSource
     */
    protected function copyMetricWeights(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        string $changeSource,
    ): int {
        $copiedCount = 0;

        foreach ($this->repository->getMetricCollection($sourceStoreName, $sourceLocaleName)->getMetrics() as $metricTransfer) {
            $idSearchRankingMetric = $metricTransfer->getIdSearchRankingMetricOrFail();
            $sourceWeight = $this->repository->findMetricWeight($idSearchRankingMetric, $sourceStoreName, $sourceLocaleName);

            if ($sourceWeight === null) {
                continue;
            }

            $this->metricWriter->saveMetricWeight($idSearchRankingMetric, $targetStoreName, $targetLocaleName, $sourceWeight, $changeSource);
            $copiedCount++;
        }

        return $copiedCount;
    }

    /**
     * Only a setting explicitly saved in the source scope is copied — one never touched there stays on
     * its code-level default in the target too, rather than writing an explicit copy of that default.
     *
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     */
    protected function copySettings(string $sourceStoreName, string $sourceLocaleName, string $targetStoreName, string $targetLocaleName): int
    {
        $copiedCount = 0;

        foreach (static::SETTING_COPIERS as $settingKey => $setterMethodName) {
            $sourceValue = $this->repository->findSettingValue($settingKey, $sourceStoreName, $sourceLocaleName);

            if ($sourceValue === null) {
                continue;
            }

            $this->settingManager->{$setterMethodName}($targetStoreName, $targetLocaleName, (float)$sourceValue);
            $copiedCount++;
        }

        return $copiedCount;
    }
}
