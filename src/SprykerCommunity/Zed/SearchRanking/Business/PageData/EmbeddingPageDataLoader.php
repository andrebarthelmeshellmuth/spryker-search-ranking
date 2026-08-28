<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\PageData;

use Generated\Shared\Transfer\ProductPageLoadTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Mirrors {@see \SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoader} exactly, for
 * the semantic embedding vector instead of business-signal scores: one bulk read per full-catalog
 * publish run, never a per-product query.
 */
class EmbeddingPageDataLoader implements EmbeddingPageDataLoaderInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingConfig $config,
    ) {
    }

    /**
     * @param \Generated\Shared\Transfer\ProductPageLoadTransfer $productPageLoadTransfer
     */
    public function expandProductPageLoadTransfer(ProductPageLoadTransfer $productPageLoadTransfer): ProductPageLoadTransfer
    {
        /** @var array<int> $productAbstractIds */
        $productAbstractIds = $productPageLoadTransfer->getProductAbstractIds();

        if ($productAbstractIds === []) {
            return $productPageLoadTransfer;
        }

        $embeddingsByIdProductAbstract = $this->repository->getEmbeddingsGroupedByIdProductAbstract(
            $productAbstractIds,
            SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
            $this->config->getEmbeddingModelId(),
        );

        foreach ($productPageLoadTransfer->getPayloadTransfers() as $payloadTransfer) {
            $embedding = $embeddingsByIdProductAbstract[$payloadTransfer->getIdProductAbstract()] ?? null;

            if ($embedding === null) {
                continue;
            }

            $payloadTransfer->setEmbedding($embedding);
        }

        return $productPageLoadTransfer;
    }
}
