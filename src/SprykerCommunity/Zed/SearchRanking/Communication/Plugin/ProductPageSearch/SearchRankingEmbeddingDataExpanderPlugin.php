<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch;

use Generated\Shared\Transfer\ProductPageSearchTransfer;
use Generated\Shared\Transfer\ProductPayloadTransfer;
use Spryker\Shared\ProductPageSearch\ProductPageSearchConfig;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\ProductPageSearch\Dependency\Plugin\ProductPageDataExpanderInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingEmbeddingDataExpanderPlugin extends AbstractPlugin implements ProductPageDataExpanderInterface
{
    /**
     * {@inheritDoc}
     * - Copies the bulk-loaded semantic embedding vector from the payload onto the product page search
     *   transfer. Leaves the field unset (not an empty array) when the payload has no vector for this
     *   product — mirrors {@see \SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingScoresDataExpanderPlugin},
     *   except a product with no ranking scores still gets an explicit `[]`, while a product with no
     *   embedding gets no field at all — an empty vector is not a meaningful "zero" the way empty scores
     *   are, and the ES-side kNN blend needs to be able to tell "no vector" apart from "all-zero vector".
     *
     * @api
     *
     * @param array<string, mixed> $productData
     * @param \Generated\Shared\Transfer\ProductPageSearchTransfer $productAbstractPageSearchTransfer
     */
    public function expandProductPageData(array $productData, ProductPageSearchTransfer $productAbstractPageSearchTransfer): void
    {
        $embedding = $this->getProductPayloadTransfer($productData)->getEmbedding();

        if ($embedding === []) {
            return;
        }

        $productAbstractPageSearchTransfer->setEmbedding($embedding);
    }

    /**
     * @param array<string, mixed> $productData
     */
    protected function getProductPayloadTransfer(array $productData): ProductPayloadTransfer
    {
        return $productData[ProductPageSearchConfig::PRODUCT_ABSTRACT_PAGE_LOAD_DATA];
    }
}
