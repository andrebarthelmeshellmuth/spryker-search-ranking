<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch;

use Generated\Shared\Transfer\ProductPageLoadTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\ProductPageSearchExtension\Dependency\Plugin\ProductPageDataLoaderPluginInterface;
use Throwable;

/**
 * The event-pipeline hook for Pass 2's entity-lookup near-live sync mode — the ONE plugin interface in the
 * product-page-search publish cycle that fires for EVERY id in a publish batch unconditionally, before
 * core's own `ProductAbstractPagePublisher::isActual()` filtering decides whether a product stays in the
 * main `page` index at all (confirmed against core's own publisher: a deactivated product's data-EXPANDER
 * and map-EXPANDER plugins are never called — `storeProductAbstractPageSearchEntity()`'s
 * `expandPageSearchTransferWithPlugins()` call sits behind that same `isActual()` check — but every
 * `ProductPageDataLoaderPluginInterface`, THIS interface, runs BEFORE it, for the whole id list). That is
 * exactly the property this plugin needs: an activation→deactivation transition must be reacted to (remove
 * the product's terms), not silently skipped the way an expander-based hook would skip it.
 *
 * Registered UNCONDITIONALLY in a project's own `Pyz\Zed\ProductPageSearch\ProductPageSearchDependencyProvider::getDataLoaderPlugins()`
 * — same "always register, the plugin itself gates on config" shape this package's Client-layer
 * `search-ranking:check-installation` already expects elsewhere, and what makes the plugin-registered-vs-config-enabled
 * distinction {@see \SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole::checkEntityLookupSyncConfiguration()}
 * relies on possible in the first place: {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::isEntityLookupEventSyncEnabled()}
 * decides whether real work happens, independent of whether the class is registered at all.
 *
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingEntityLookupSyncPlugin extends AbstractPlugin implements ProductPageDataLoaderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Returns `$productPageLoadTransfer` unmodified either way — this plugin exists purely for its side
     *   effect (syncing the entity-lookup index), never to contribute page data.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ProductPageLoadTransfer $productPageLoadTransfer
     */
    public function expandProductPageDataTransfer(ProductPageLoadTransfer $productPageLoadTransfer): ProductPageLoadTransfer
    {
        if (!$this->getConfig()->isEntityLookupEventSyncEnabled()) {
            return $productPageLoadTransfer;
        }

        try {
            $this->getFacade()->syncEntityLookupForProductAbstracts($productPageLoadTransfer->getProductAbstractIds());
        } catch (Throwable) {
            // The entity-lookup index is a SECONDARY, best-effort structure (SKU/brand/category term
            // dictionary for query-time intent detection) — it is rebuilt in full by
            // `search-ranking:suggest-index-entity-lookup:rebuild` and self-heals on the next publish of
            // each product. A transient failure here (e.g. Elastica marking a connection dead under heavy
            // reindex load) must never fail the surrounding product-page publish, which owns the primary
            // `page` search index. Same degrade-silently posture this package already takes for every other
            // best-effort signal (semantic query-embedding cache, in-memory entity lookup).
        }

        return $productPageLoadTransfer;
    }
}
