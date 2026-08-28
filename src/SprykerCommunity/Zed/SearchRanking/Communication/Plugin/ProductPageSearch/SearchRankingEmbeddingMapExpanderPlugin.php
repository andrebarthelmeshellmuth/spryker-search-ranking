<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch;

use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\PageMapTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\ProductPageSearchExtension\Dependency\PageMapBuilderInterface;
use Spryker\Zed\ProductPageSearchExtension\Dependency\Plugin\ProductAbstractMapExpanderPluginInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingEmbeddingMapExpanderPlugin extends AbstractPlugin implements ProductAbstractMapExpanderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Writes the semantic embedding vector into the page document's `embedding` field (a flat float
     *   list, matching the `knn_vector` mapping in `page.json`); products without a stored vector get no
     *   `embedding` field at all — never a zero/empty vector, since that would be indistinguishable from a
     *   real (if degenerate) embedding at query time.
     * - Looks the vector up directly for THIS product's real `($productData['store'], $localeTransfer)`,
     *   via {@see \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface::findEmbeddingForProduct()},
     *   rather than reading a pre-populated `$productData` field: the upstream data-loader/data-expander
     *   stage (`EmbeddingPageDataLoader`/`SearchRankingEmbeddingDataExpanderPlugin`) runs BEFORE
     *   per-locale iteration and has no locale to scope by at all — `ProductPageLoadTransfer` carries no
     *   store/locale property, confirmed against the core transfer definition — so anything it prefetches
     *   is necessarily locale-blind (this package's own `DEFAULT_SCOPE_STORE_NAME`/`DEFAULT_SCOPE_LOCALE_NAME`
     *   safety-valve, `DE`/`de_DE`). That's an acceptable simplification for business-signal metrics
     *   (`ScoresPageDataLoader` uses the exact same default, deliberately — a metric weight is store-wide,
     *   language-agnostic data), but embeddings are inherently per-language text, so silently reusing a
     *   German-language vector on every other locale's page document would be wrong, not just
     *   inefficient. This stage is the first one that actually receives a real `$localeTransfer` per
     *   product, so it does its own correctly-scoped lookup instead of trusting the locale-blind one.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\PageMapTransfer $pageMapTransfer
     * @param \Spryker\Zed\ProductPageSearchExtension\Dependency\PageMapBuilderInterface $pageMapBuilder
     * @param array<string, mixed> $productData
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- signature is fixed by ProductAbstractMapExpanderPluginInterface.
    public function expandProductMap(
        PageMapTransfer $pageMapTransfer,
        PageMapBuilderInterface $pageMapBuilder,
        array $productData,
        LocaleTransfer $localeTransfer,
    ): PageMapTransfer {
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
        $embedding = $this->getFacade()->findEmbeddingForProduct(
            (int)$productData['id_product_abstract'],
            (string)$productData['store'],
            $localeTransfer->getLocaleNameOrFail(),
        );

        if ($embedding !== null && $embedding !== []) {
            $pageMapTransfer->setEmbedding($embedding);
        }

        return $pageMapTransfer;
    }
}
