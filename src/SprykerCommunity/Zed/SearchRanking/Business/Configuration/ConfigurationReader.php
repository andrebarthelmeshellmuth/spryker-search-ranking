<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Configuration;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * The single place the live Zed-side ranking configuration gets assembled into
 * `SearchRankingConfigurationStorageTransfer`. Every consumer that needs "the whole configuration for
 * this scope" — the storage publisher here, `search-ranking-optimizer`'s live baseline and its run-form
 * parameter checklist — reads it through this one assembler, so adding a knob means touching the transfer
 * and this class, not every consumer's own hand-assembly.
 */
class ConfigurationReader implements ConfigurationReaderInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface $settingManager
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SettingManagerInterface $settingManager,
        protected SearchRankingConfig $config,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $storeName
     * @param string $localeName
     */
    public function getConfiguration(string $storeName, string $localeName): SearchRankingConfigurationStorageTransfer
    {
        return (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights($this->getActiveMetricWeights($storeName, $localeName))
            ->setRelevanceWeight($this->settingManager->getRelevanceWeight($storeName, $localeName))
            ->setRelevanceSaturationPoint($this->settingManager->getRelevanceSaturationPoint($storeName, $localeName))
            ->setSpecificityBlendWeight($this->settingManager->getSpecificityBlendWeight($storeName, $localeName))
            ->setSpecificitySaturationPoint($this->settingManager->getSpecificitySaturationPoint($storeName, $localeName))
            ->setSpecificityCurveExponent($this->settingManager->getSpecificityCurveExponent($storeName, $localeName))
            ->setSpecificityWeightExponent($this->settingManager->getSpecificityWeightExponent($storeName, $localeName))
            ->setSpecificityWeightShiftMagnitude($this->settingManager->getSpecificityWeightShiftMagnitude($storeName, $localeName))
            ->setRandomMetricName($this->config->getRandomMetricName());
    }

    /**
     * Raw, un-normalized weights exactly as saved — the published storefront document normalizes them to
     * sum to 1 on top of this (see {@see \SprykerCommunity\Zed\SearchRankingStorage\Business\Writer\RankingConfigurationStorageWriter}),
     * a derived view this read must not pre-empt: a consumer tuning weights needs the numbers a curator
     * actually entered.
     *
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<string, float>
     */
    protected function getActiveMetricWeights(string $storeName, string $localeName): array
    {
        $collectionTransfer = $this->repository->attachWeights(
            $this->repository->getActiveMetricCollection($storeName, $localeName),
            $storeName,
            $localeName,
        );

        $metricWeights = [];

        foreach ($collectionTransfer->getMetrics() as $metricTransfer) {
            $metricWeights[$metricTransfer->getNameOrFail()] = $metricTransfer->getWeightOrFail();
        }

        return $metricWeights;
    }
}
