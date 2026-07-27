<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Publisher;

use Generated\Shared\Transfer\EventEntityTransfer;
use Spryker\Zed\Product\Dependency\ProductEvents;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

class ProductAbstractScorePublisher implements ProductAbstractScorePublisherInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface $eventFacade
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingToEventFacadeInterface $eventFacade,
        protected SearchRankingConfig $config,
    ) {
    }

    /**
     * @return int
     */
    public function publishScoredProductAbstracts(): int
    {
        $productAbstractIds = $this->repository->getProductAbstractIdsWithActiveMetricValues();

        foreach (array_chunk($productAbstractIds, $this->config->getPublishEventChunkSize()) as $productAbstractIdsChunk) {
            $eventEntityTransfers = [];

            foreach ($productAbstractIdsChunk as $idProductAbstract) {
                $eventEntityTransfers[] = (new EventEntityTransfer())->setId($idProductAbstract);
            }

            $this->eventFacade->triggerBulk(ProductEvents::PRODUCT_ABSTRACT_PUBLISH, $eventEntityTransfers);
        }

        return count($productAbstractIds);
    }
}
