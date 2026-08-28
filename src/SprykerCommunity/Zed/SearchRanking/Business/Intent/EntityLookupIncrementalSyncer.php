<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;

class EntityLookupIncrementalSyncer implements EntityLookupIncrementalSyncerInterface
{
    /**
     * @param array<\SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface> $entityCorpusReaderPlugins
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupIndexerInterface $indexer
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface $storeFacade
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupSuggestIndexNameResolverInterface $indexNameResolver
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Intent\ProductAbstractStoreResolverInterface $productAbstractStoreResolver
     */
    public function __construct(
        protected array $entityCorpusReaderPlugins,
        protected SuggestIndexEntityLookupIndexerInterface $indexer,
        protected SearchRankingToStoreFacadeInterface $storeFacade,
        protected EntityLookupSuggestIndexNameResolverInterface $indexNameResolver,
        protected ProductAbstractStoreResolverInterface $productAbstractStoreResolver,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param array<int> $idProductAbstracts
     */
    public function sync(array $idProductAbstracts): void
    {
        if ($idProductAbstracts === []) {
            return;
        }

        $incrementalPlugins = $this->getIncrementalEntityCorpusReaderPlugins();

        if ($incrementalPlugins === []) {
            return;
        }

        $storeNamesByIdStore = $this->getStoreNamesByIdStore();

        foreach ($idProductAbstracts as $idProductAbstract) {
            $this->syncOneProductAbstract($idProductAbstract, $incrementalPlugins, $storeNamesByIdStore);
        }
    }

    /**
     * @param int $idProductAbstract
     * @param array<\SprykerCommunity\Zed\SearchRanking\Business\Intent\IncrementalEntityCorpusReaderPluginInterface> $incrementalPlugins
     * @param array<int, string> $storeNamesByIdStore
     */
    protected function syncOneProductAbstract(int $idProductAbstract, array $incrementalPlugins, array $storeNamesByIdStore): void
    {
        foreach ($this->productAbstractStoreResolver->getIdStoresForProductAbstract($idProductAbstract) as $idStore) {
            if (!isset($storeNamesByIdStore[$idStore])) {
                continue;
            }

            $indexName = $this->indexNameResolver->resolveIndexName($storeNamesByIdStore[$idStore]);
            $this->indexer->ensureIndexExists($indexName);

            foreach ($incrementalPlugins as $plugin) {
                $this->syncOneTypeForOneProduct($plugin, $idProductAbstract, $idStore, $indexName);
            }
        }
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Intent\IncrementalEntityCorpusReaderPluginInterface $plugin
     * @param int $idProductAbstract
     * @param int $idStore
     * @param string $indexName
     */
    protected function syncOneTypeForOneProduct(
        IncrementalEntityCorpusReaderPluginInterface $plugin,
        int $idProductAbstract,
        int $idStore,
        string $indexName,
    ): void {
        $terms = $plugin->getTermsForProductAbstract($idProductAbstract);

        if ($terms === []) {
            return;
        }

        if ($plugin->isProductAbstractActive($idProductAbstract)) {
            $this->indexer->upsertTerms($indexName, $plugin->getEntityType(), $terms);

            return;
        }

        $termsToRemove = array_values(array_filter(
            $terms,
            fn (string $term): bool => !$plugin->isTermStillUsedElsewhere($term, $idProductAbstract, $idStore),
        ));

        if ($termsToRemove === []) {
            return;
        }

        $this->indexer->removeTerms($indexName, $plugin->getEntityType(), $termsToRemove);
    }

    /**
     * @return array<\SprykerCommunity\Zed\SearchRanking\Business\Intent\IncrementalEntityCorpusReaderPluginInterface>
     */
    protected function getIncrementalEntityCorpusReaderPlugins(): array
    {
        return array_values(array_filter(
            $this->entityCorpusReaderPlugins,
            static fn (EntityCorpusReaderPluginInterface $plugin): bool => $plugin instanceof IncrementalEntityCorpusReaderPluginInterface,
        ));
    }

    /**
     * @return array<int, string>
     */
    protected function getStoreNamesByIdStore(): array
    {
        $storeNamesByIdStore = [];

        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            $storeNamesByIdStore[$storeTransfer->getIdStoreOrFail()] = $storeTransfer->getNameOrFail();
        }

        return $storeNamesByIdStore;
    }
}
