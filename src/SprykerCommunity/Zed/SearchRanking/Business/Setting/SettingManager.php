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
    public function getScoreFloor(): float
    {
        $settingValue = $this->repository->findSettingValue(SharedSearchRankingConfig::SETTING_KEY_SCORE_FLOOR);

        if ($settingValue === null) {
            return $this->config->getDefaultScoreFloor();
        }

        return (float)$settingValue;
    }

    /**
     * @param float $scoreFloor
     *
     * @return void
     */
    public function saveScoreFloor(float $scoreFloor): void
    {
        $this->entityManager->saveSetting(
            SharedSearchRankingConfig::SETTING_KEY_SCORE_FLOOR,
            (string)$scoreFloor,
        );
    }
}
