<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\PageData;

use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Backs {@see \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface::findEmbeddingForProduct()}
 * — a single-product, real-locale-scoped lookup for the product-page-search MAP EXPANDER stage, as opposed
 * to {@see \SprykerCommunity\Zed\SearchRanking\Business\PageData\EmbeddingPageDataLoader}'s bulk,
 * locale-blind DATA LOADER stage lookup. See {@see \SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingEmbeddingMapExpanderPlugin}
 * for why both exist.
 */
class ProductEmbeddingFinder implements ProductEmbeddingFinderInterface
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
     * @param int $idProductAbstract
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<int, float>|null
     */
    public function find(int $idProductAbstract, string $storeName, string $localeName): ?array
    {
        return $this->repository->getEmbeddingsGroupedByIdProductAbstract(
            [$idProductAbstract],
            $storeName,
            $localeName,
            $this->config->getEmbeddingModelId(),
        )[$idProductAbstract] ?? null;
    }
}
