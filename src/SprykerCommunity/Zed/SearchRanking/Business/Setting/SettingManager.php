<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Setting;

use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

class SettingManager implements SettingManagerInterface
{
    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface
     */
    protected SearchRankingRepositoryInterface $repository;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface
     */
    protected SearchRankingEntityManagerInterface $entityManager;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig
     */
    protected SearchRankingConfig $config;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     */
    public function __construct(
        SearchRankingRepositoryInterface $repository,
        SearchRankingEntityManagerInterface $entityManager,
        SearchRankingConfig $config,
    ) {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
        $this->config = $config;
    }

    /**
     * @return float
     */
    public function getRelevanceWeight(): float
    {
        $settingValue = $this->repository->findSettingValue(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT);

        if ($settingValue === null) {
            return $this->config->getDefaultRelevanceWeight();
        }

        return (float)$settingValue;
    }

    /**
     * @param float $relevanceWeight
     *
     * @return void
     */
    public function saveRelevanceWeight(float $relevanceWeight): void
    {
        $this->entityManager->saveSetting(
            SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT,
            (string)$relevanceWeight,
        );
    }

    /**
     * @return float
     */
    public function getRelevanceSaturationPoint(): float
    {
        $settingValue = $this->repository->findSettingValue(SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT);

        if ($settingValue === null) {
            return $this->config->getDefaultRelevanceSaturationPoint();
        }

        return (float)$settingValue;
    }

    /**
     * @param float $relevanceSaturationPoint
     *
     * @return void
     */
    public function saveRelevanceSaturationPoint(float $relevanceSaturationPoint): void
    {
        $this->entityManager->saveSetting(
            SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT,
            (string)$relevanceSaturationPoint,
        );
    }
}
