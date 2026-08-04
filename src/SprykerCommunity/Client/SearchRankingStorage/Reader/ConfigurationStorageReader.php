<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRankingStorage\Reader;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SynchronizationDataTransfer;
use SprykerCommunity\Client\SearchRankingStorage\Dependency\Client\SearchRankingStorageToStorageClientInterface;
use SprykerCommunity\Client\SearchRankingStorage\Dependency\Service\SearchRankingStorageToSynchronizationServiceInterface;
use SprykerCommunity\Shared\SearchRankingStorage\SearchRankingStorageConfig;

class ConfigurationStorageReader implements ConfigurationStorageReaderInterface
{
    /**
     * @var string
     */
    protected const KEY_METRIC_WEIGHTS = 'metric_weights';

    /**
     * @var string
     */
    protected const KEY_RELEVANCE_WEIGHT = 'relevance_weight';

    /**
     * @var string
     */
    protected const KEY_RELEVANCE_SATURATION_POINT = 'relevance_saturation_point';

    /**
     * @var string
     */
    protected const KEY_SPECIFICITY_BLEND_WEIGHT = 'specificity_blend_weight';

    /**
     * @var string
     */
    protected const KEY_SPECIFICITY_SATURATION_POINT = 'specificity_saturation_point';

    /**
     * @var string
     */
    protected const KEY_SPECIFICITY_WEIGHT_EXPONENT = 'specificity_weight_exponent';

    /**
     * @var string
     */
    protected const KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE = 'specificity_weight_shift_magnitude';

    /**
     * Defaults for a KV payload published before this feature existed — matches
     * `SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getDefaultRelevanceWeight()` (see that
     * method's own docblock for why 0.75, not a neutral 0.5), so a payload predating this key still gets
     * the same text-favoring split a fresh Zed save would produce, rather than silently collapsing to
     * business-signals-only.
     *
     * @var float
     */
    protected const DEFAULT_RELEVANCE_WEIGHT = 0.75;

    /**
     * Defaults for a KV payload published before this feature existed — matches
     * `SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getDefaultRelevanceSaturationPoint()`, so
     * a payload from before this key existed still gets a real, sane saturation point rather than `0.0`
     * — which would make the painless script's `_score / (_score + relevanceSaturationPoint)` term
     * degenerate to a constant `1.0` (or `NaN` for a `_score` of exactly `0`) instead of the intended
     * saturating curve.
     *
     * @var float
     */
    protected const DEFAULT_RELEVANCE_SATURATION_POINT = 12.0;

    /**
     * Defaults for a KV payload published before this feature existed — matches
     * `SprykerCommunity\Zed\SearchRanking\SearchRankingConfig`'s own defaults, so a shop that enables
     * specificity weighting before its first post-upgrade republish still gets the same values it would
     * have gotten from a fresh Zed save, not an arbitrary fallback.
     *
     * @var float
     */
    protected const DEFAULT_SPECIFICITY_BLEND_WEIGHT = 0.7;

    /**
     * Matches `SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getDefaultSpecificitySaturationPoint()`
     * — an unmeasured placeholder until the shop runs Calibration, same role as
     * `DEFAULT_RELEVANCE_SATURATION_POINT` plays for `relevanceSaturationPoint`.
     *
     * @var float
     */
    protected const DEFAULT_SPECIFICITY_SATURATION_POINT = 3.0;

    /**
     * @var float
     */
    protected const DEFAULT_SPECIFICITY_WEIGHT_EXPONENT = 1.0;

    /**
     * Matches `SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getDefaultSpecificityWeightShiftMagnitude()`
     * — see that method's own docblock for why 0.25 (sized to the 0.75 relevance-weight baseline), not 0.5.
     *
     * @var float
     */
    protected const DEFAULT_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE = 0.25;

    /**
     * Memoized per request, keyed by `"{$storeName}:{$localeName}"`; a missing key = not read yet for
     * that scope (null is a valid "no document" result for a scope). `static`, not instance-level,
     * matching Spryker core's own client-reader caching convention — safe under the traditional
     * process-per-request PHP-FPM model this project runs on, but would leak stale configuration across
     * requests under a persistent-worker runtime (RoadRunner/Swoole), since nothing ever resets it
     * mid-process.
     *
     * @var array<string, \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer|null>
     */
    protected static array $rankingConfigurationCache = [];

    /**
     * @param \SprykerCommunity\Client\SearchRankingStorage\Dependency\Client\SearchRankingStorageToStorageClientInterface $storageClient
     * @param \SprykerCommunity\Client\SearchRankingStorage\Dependency\Service\SearchRankingStorageToSynchronizationServiceInterface $synchronizationService
     */
    public function __construct(
        protected SearchRankingStorageToStorageClientInterface $storageClient,
        protected SearchRankingStorageToSynchronizationServiceInterface $synchronizationService,
    ) {
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function findRankingConfiguration(string $storeName, string $localeName): ?SearchRankingConfigurationStorageTransfer
    {
        $cacheKey = sprintf('%s:%s', $storeName, $localeName);

        if (array_key_exists($cacheKey, static::$rankingConfigurationCache)) {
            return static::$rankingConfigurationCache[$cacheKey];
        }

        static::$rankingConfigurationCache[$cacheKey] = $this->readRankingConfiguration($storeName, $localeName);

        return static::$rankingConfigurationCache[$cacheKey];
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    protected function readRankingConfiguration(string $storeName, string $localeName): ?SearchRankingConfigurationStorageTransfer
    {
        $storageKey = $this->synchronizationService
            ->getStorageKeyBuilder(SearchRankingStorageConfig::SEARCH_RANKING_CONFIGURATION_RESOURCE_NAME)
            ->generateKey(
                (new SynchronizationDataTransfer())
                    ->setStore($storeName)
                    ->setLocale($localeName),
            );

        $configurationData = $this->storageClient->get($storageKey);

        if (!is_array($configurationData)) {
            return null;
        }

        return (new SearchRankingConfigurationStorageTransfer())
            ->setMetricWeights($configurationData[static::KEY_METRIC_WEIGHTS] ?? [])
            ->setRelevanceWeight((float)($configurationData[static::KEY_RELEVANCE_WEIGHT] ?? static::DEFAULT_RELEVANCE_WEIGHT))
            ->setRelevanceSaturationPoint((float)($configurationData[static::KEY_RELEVANCE_SATURATION_POINT] ?? static::DEFAULT_RELEVANCE_SATURATION_POINT))
            ->setSpecificityBlendWeight((float)($configurationData[static::KEY_SPECIFICITY_BLEND_WEIGHT] ?? static::DEFAULT_SPECIFICITY_BLEND_WEIGHT))
            ->setSpecificitySaturationPoint((float)($configurationData[static::KEY_SPECIFICITY_SATURATION_POINT] ?? static::DEFAULT_SPECIFICITY_SATURATION_POINT))
            ->setSpecificityWeightExponent((float)($configurationData[static::KEY_SPECIFICITY_WEIGHT_EXPONENT] ?? static::DEFAULT_SPECIFICITY_WEIGHT_EXPONENT))
            ->setSpecificityWeightShiftMagnitude((float)($configurationData[static::KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE] ?? static::DEFAULT_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE));
    }
}
