<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Setting;

use Generated\Shared\Transfer\SearchRankingSettingHistoryTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

class SettingManager implements SettingManagerInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingEntityManagerInterface $entityManager,
        protected SearchRankingConfig $config,
    ) {
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceWeight(string $storeName, string $localeName): float
    {
        $settingValue = $this->repository->findSettingValue(
            SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT,
            $storeName,
            $localeName,
        );

        if ($settingValue === null) {
            return $this->config->getDefaultRelevanceWeight();
        }

        return (float)$settingValue;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceWeight
     */
    public function saveRelevanceWeight(string $storeName, string $localeName, float $relevanceWeight): void
    {
        $this->saveSettingWithHistory(
            SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT,
            $storeName,
            $localeName,
            (string)$relevanceWeight,
        );
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getRelevanceSaturationPoint(string $storeName, string $localeName): float
    {
        $settingValue = $this->repository->findSettingValue(
            SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT,
            $storeName,
            $localeName,
        );

        if ($settingValue === null) {
            return $this->config->getDefaultRelevanceSaturationPoint();
        }

        return (float)$settingValue;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $relevanceSaturationPoint
     */
    public function saveRelevanceSaturationPoint(string $storeName, string $localeName, float $relevanceSaturationPoint): void
    {
        $this->saveSettingWithHistory(
            SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT,
            $storeName,
            $localeName,
            (string)$relevanceSaturationPoint,
        );
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityBlendWeight(string $storeName, string $localeName): float
    {
        $settingValue = $this->repository->findSettingValue(
            SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT,
            $storeName,
            $localeName,
        );

        if ($settingValue === null) {
            return $this->config->getDefaultSpecificityBlendWeight();
        }

        return (float)$settingValue;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityBlendWeight
     */
    public function saveSpecificityBlendWeight(string $storeName, string $localeName, float $specificityBlendWeight): void
    {
        $this->saveSettingWithHistory(
            SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT,
            $storeName,
            $localeName,
            (string)$specificityBlendWeight,
        );
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificitySaturationPoint(string $storeName, string $localeName): float
    {
        $settingValue = $this->repository->findSettingValue(
            SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT,
            $storeName,
            $localeName,
        );

        if ($settingValue === null) {
            return $this->config->getDefaultSpecificitySaturationPoint();
        }

        return (float)$settingValue;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificitySaturationPoint
     */
    public function saveSpecificitySaturationPoint(string $storeName, string $localeName, float $specificitySaturationPoint): void
    {
        $this->saveSettingWithHistory(
            SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT,
            $storeName,
            $localeName,
            (string)$specificitySaturationPoint,
        );
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightExponent(string $storeName, string $localeName): float
    {
        $settingValue = $this->repository->findSettingValue(
            SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT,
            $storeName,
            $localeName,
        );

        if ($settingValue === null) {
            return $this->config->getDefaultSpecificityWeightExponent();
        }

        return (float)$settingValue;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightExponent
     */
    public function saveSpecificityWeightExponent(string $storeName, string $localeName, float $specificityWeightExponent): void
    {
        $this->saveSettingWithHistory(
            SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT,
            $storeName,
            $localeName,
            (string)$specificityWeightExponent,
        );
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function getSpecificityWeightShiftMagnitude(string $storeName, string $localeName): float
    {
        $settingValue = $this->repository->findSettingValue(
            SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE,
            $storeName,
            $localeName,
        );

        if ($settingValue === null) {
            return $this->config->getDefaultSpecificityWeightShiftMagnitude();
        }

        return (float)$settingValue;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param float $specificityWeightShiftMagnitude
     */
    public function saveSpecificityWeightShiftMagnitude(string $storeName, string $localeName, float $specificityWeightShiftMagnitude): void
    {
        $this->saveSettingWithHistory(
            SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE,
            $storeName,
            $localeName,
            (string)$specificityWeightShiftMagnitude,
        );
    }

    /**
     * Writes the setting and, if its value actually changed, records a history snapshot at the same
     * (settingKey, store, locale) scope — the same "diff, then write, then record" shape
     * {@see \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriter::saveMetricWeight()} uses for
     * per-metric weights.
     *
     * @param string $settingKey
     * @param string $storeName
     * @param string $localeName
     * @param string $settingValue
     */
    protected function saveSettingWithHistory(string $settingKey, string $storeName, string $localeName, string $settingValue): void
    {
        $previousSettingValue = $this->repository->findSettingValue($settingKey, $storeName, $localeName);

        $this->entityManager->saveSetting($settingKey, $storeName, $localeName, $settingValue);

        if ($previousSettingValue === $settingValue) {
            return;
        }

        $this->entityManager->recordSettingHistory(
            (new SearchRankingSettingHistoryTransfer())
                ->setSettingKey($settingKey)
                ->setStoreName($storeName)
                ->setLocaleName($localeName)
                ->setSettingValue($settingValue),
        );
    }
}
