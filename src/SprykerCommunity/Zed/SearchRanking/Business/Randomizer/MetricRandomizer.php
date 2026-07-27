<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Randomizer;

use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisherInterface;
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
     * @param string $metricName
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected ProductMetricNormalizerInterface $normalizer,
        protected ProductAbstractScorePublisherInterface $publisher,
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
     * @return bool
     */
    public function randomizeIfActive(): bool
    {
        $metricTransfer = $this->repository->findMetricByName($this->metricName);

        if ($metricTransfer === null || !$metricTransfer->getIsActive()) {
            return false;
        }

        $this->normalizer->normalizeMetric($metricTransfer);
        $this->publisher->publishScoredProductAbstracts();

        return true;
    }
}
