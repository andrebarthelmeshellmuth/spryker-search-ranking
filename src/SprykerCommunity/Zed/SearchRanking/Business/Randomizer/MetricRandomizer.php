<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Randomizer;

use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisherInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Backs `search-ranking:randomize`. Deliberately reuses the SAME full-product-republish path
 * `ProductAbstractScorePublisherInterface` already uses for every other business-signal change, rather
 * than a narrower "products with a value for this one metric" query or a partial Elasticsearch write —
 * see the README's "Why full republish, not a partial score-only ES update" for the full rationale
 * (in short: a partial ES update is not actually cheaper at the storage layer, and a full republish keeps
 * the resilient publish/queue path plus its incidental self-healing of other product drift).
 */
class MetricRandomizer implements MetricRandomizerInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface $normalizer
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisherInterface $publisher
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface $storeFacade
     * @param string $metricName
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected ProductMetricNormalizerInterface $normalizer,
        protected ProductAbstractScorePublisherInterface $publisher,
        protected SearchRankingToStoreFacadeInterface $storeFacade,
        /**
         * Baked in at construction (from {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getRandomMetricName()},
         * resolved by the business factory) rather than taken per-call — `AbstractFacade` has no `getConfig()`
         * of its own, only `AbstractBusinessFactory` does, so resolving it here keeps that dependency where it
         * already works instead of the facade reaching for a method it does not have.
         */
        protected string $metricName,
    ) {
    }

    /**
     * Fans out over every store×locale (mirroring {@see \SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizer::normalize()}'s
     * own fan-out) but republishes only once at the end — a full catalog republish is expensive enough that
     * doing it once after every scope has been re-randomized is worth the extra bookkeeping.
     *
     * @param string|null $filterStoreName
     * @param string|null $filterLocaleName
     */
    public function randomizeIfActive(?string $filterStoreName = null, ?string $filterLocaleName = null): bool
    {
        $wasAnyScopeRandomized = false;

        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            $storeName = $storeTransfer->getNameOrFail();

            if ($filterStoreName !== null && $storeName !== $filterStoreName) {
                continue;
            }

            foreach ($storeTransfer->getAvailableLocaleIsoCodes() as $localeName) {
                if ($filterLocaleName !== null && $localeName !== $filterLocaleName) {
                    continue;
                }

                if (!$this->randomizeScopeIfActive($storeName, $localeName)) {
                    continue;
                }

                $wasAnyScopeRandomized = true;
            }
        }

        if ($wasAnyScopeRandomized) {
            $this->publisher->publishScoredProductAbstracts();
        }

        return $wasAnyScopeRandomized;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    protected function randomizeScopeIfActive(string $storeName, string $localeName): bool
    {
        $metricTransfer = $this->repository->findMetricByName($this->metricName, $storeName, $localeName);

        if ($metricTransfer === null || !$metricTransfer->getIsActive()) {
            return false;
        }

        $this->normalizer->normalizeMetric($metricTransfer, $storeName, $localeName);

        return true;
    }
}
