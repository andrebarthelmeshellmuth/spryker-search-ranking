<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToLocaleFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;

class SuggestIndexEntityLookupRebuilder implements SuggestIndexEntityLookupRebuilderInterface
{
    /**
     * @param array<\SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface> $entityCorpusReaderPlugins
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupIndexerInterface $indexer
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface $storeFacade
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToLocaleFacadeInterface $localeFacade
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupSuggestIndexNameResolverInterface $indexNameResolver
     */
    public function __construct(
        protected array $entityCorpusReaderPlugins,
        protected SuggestIndexEntityLookupIndexerInterface $indexer,
        protected SearchRankingToStoreFacadeInterface $storeFacade,
        protected SearchRankingToLocaleFacadeInterface $localeFacade,
        protected EntityLookupSuggestIndexNameResolverInterface $indexNameResolver,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $type
     * @param string|null $filterStoreName
     * @param string|null $filterLocaleName
     *
     * @return array<string, int>
     */
    public function rebuild(string $type, ?string $filterStoreName, ?string $filterLocaleName): array
    {
        if (!$this->supportsType($type)) {
            return [];
        }

        $idLocale = $filterLocaleName !== null ? $this->resolveIdLocale($filterLocaleName) : null;

        if ($filterLocaleName !== null && $idLocale === null) {
            return [];
        }

        $writtenCountByStoreName = [];

        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            $storeName = $storeTransfer->getNameOrFail();

            if ($filterStoreName !== null && $storeName !== $filterStoreName) {
                continue;
            }

            $terms = $this->readCorpus($type, $storeTransfer->getIdStoreOrFail(), $idLocale);
            $indexName = $this->indexNameResolver->resolveIndexName($storeName);

            $this->indexer->ensureIndexExists($indexName);
            $writtenCountByStoreName[$storeName] = $this->indexer->replaceTerms($indexName, $type, $terms);
        }

        return $writtenCountByStoreName;
    }

    /**
     * @param string $type
     * @param int $idStore
     * @param int|null $idLocale
     *
     * @return array<int, string>
     */
    protected function readCorpus(string $type, int $idStore, ?int $idLocale): array
    {
        foreach ($this->entityCorpusReaderPlugins as $entityCorpusReaderPlugin) {
            if ($entityCorpusReaderPlugin->getEntityType() === $type) {
                return $entityCorpusReaderPlugin->getTerms($idStore, $idLocale);
            }
        }

        return [];
    }

    /**
     * @param string $type
     */
    protected function supportsType(string $type): bool
    {
        foreach ($this->entityCorpusReaderPlugins as $entityCorpusReaderPlugin) {
            if ($entityCorpusReaderPlugin->getEntityType() === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $localeName
     */
    protected function resolveIdLocale(string $localeName): ?int
    {
        foreach ($this->localeFacade->getLocaleCollection() as $localeTransfer) {
            if ($localeTransfer->getLocaleName() === $localeName) {
                return $localeTransfer->getIdLocale();
            }
        }

        return null;
    }
}
