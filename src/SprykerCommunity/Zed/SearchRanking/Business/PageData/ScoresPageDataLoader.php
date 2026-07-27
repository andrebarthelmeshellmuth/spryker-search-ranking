<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\PageData;

use Generated\Shared\Transfer\ProductPageLoadTransfer;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

class ScoresPageDataLoader implements ScoresPageDataLoaderInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     */
    public function __construct(protected SearchRankingRepositoryInterface $repository)
    {
    }

    /**
     * @param \Generated\Shared\Transfer\ProductPageLoadTransfer $productPageLoadTransfer
     *
     * @return \Generated\Shared\Transfer\ProductPageLoadTransfer
     */
    public function expandProductPageLoadTransfer(ProductPageLoadTransfer $productPageLoadTransfer): ProductPageLoadTransfer
    {
        /** @var array<int> $productAbstractIds */
        $productAbstractIds = $productPageLoadTransfer->getProductAbstractIds();

        if ($productAbstractIds === []) {
            return $productPageLoadTransfer;
        }

        $scoresByIdProductAbstract = $this->repository->getNormalizedScoresGroupedByIdProductAbstract($productAbstractIds);

        foreach ($productPageLoadTransfer->getPayloadTransfers() as $payloadTransfer) {
            $payloadTransfer->setSearchRankingScores(
                $scoresByIdProductAbstract[$payloadTransfer->getIdProductAbstract()] ?? [],
            );
        }

        return $productPageLoadTransfer;
    }
}
