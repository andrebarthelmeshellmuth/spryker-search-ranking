<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Embedding;

use Orm\Zed\Locale\Persistence\SpyLocaleQuery;
use Orm\Zed\Product\Persistence\SpyProductAbstractLocalizedAttributesQuery;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingClientInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Backs `search-ranking:embeddings:generate`. Pulls product text directly from Propel (there is no clean
 * standalone description field on the live ES document — it's flattened into `full-text`), never from the
 * search index itself.
 */
class EmbeddingGenerator implements EmbeddingGeneratorInterface
{
    /**
     * @var array<string, int>
     */
    protected array $idLocaleByLocaleName = [];

    /**
     * @param \SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingClientInterface $embeddingClient
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface $storeFacade
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     */
    public function __construct(
        protected EmbeddingClientInterface $embeddingClient,
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingEntityManagerInterface $entityManager,
        protected SearchRankingToStoreFacadeInterface $storeFacade,
        protected SearchRankingConfig $config,
    ) {
    }

    /**
     * @param string|null $filterStoreName
     * @param string|null $filterLocaleName
     *
     * @return array{generated: int, skipped: int, failed: int, failures: array<int, string>}
     */
    public function generate(?string $filterStoreName = null, ?string $filterLocaleName = null): array
    {
        $stats = ['generated' => 0, 'skipped' => 0, 'failed' => 0, 'failures' => []];

        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            $storeName = $storeTransfer->getNameOrFail();

            if ($filterStoreName !== null && $storeName !== $filterStoreName) {
                continue;
            }

            foreach ($storeTransfer->getAvailableLocaleIsoCodes() as $localeName) {
                if ($filterLocaleName !== null && $localeName !== $filterLocaleName) {
                    continue;
                }

                $scopeStats = $this->generateForScope($storeName, $localeName);
                $stats['generated'] += $scopeStats['generated'];
                $stats['skipped'] += $scopeStats['skipped'];
                $stats['failed'] += $scopeStats['failed'];
                $stats['failures'] += $scopeStats['failures'];
            }
        }

        return $stats;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return array{generated: int, skipped: int, failed: int, failures: array<int, string>}
     */
    protected function generateForScope(string $storeName, string $localeName): array
    {
        $scopeStats = ['generated' => 0, 'skipped' => 0, 'failed' => 0, 'failures' => []];
        $idLocale = $this->resolveIdLocale($localeName);

        if ($idLocale === null) {
            return $scopeStats;
        }

        $modelId = $this->config->getEmbeddingModelId();

        $localizedAttributesEntities = SpyProductAbstractLocalizedAttributesQuery::create()
            ->filterByFkLocale($idLocale)
            ->find();

        foreach ($localizedAttributesEntities as $localizedAttributesEntity) {
            $idProductAbstract = $localizedAttributesEntity->getFkProductAbstract();
            $name = (string)$localizedAttributesEntity->getName();
            $description = trim(strip_tags((string)$localizedAttributesEntity->getDescription()));
            $textHash = hash('sha256', $name . '|' . $description);

            $existingTextHash = $this->repository->findEmbeddingTextHash($idProductAbstract, $storeName, $localeName, $modelId);

            if ($existingTextHash === $textHash) {
                $scopeStats['skipped']++;

                continue;
            }

            try {
                $vector = $this->embeddingClient->embed(trim($name . '. ' . $description));
            } catch (EmbeddingUnavailableException $embeddingUnavailableException) {
                $scopeStats['failed']++;
                $scopeStats['failures'][$idProductAbstract] = $embeddingUnavailableException->getMessage();

                continue;
            }

            $this->entityManager->upsertEmbedding($idProductAbstract, $storeName, $localeName, $modelId, $vector, $textHash);
            $scopeStats['generated']++;
        }

        return $scopeStats;
    }

    /**
     * @param string $localeName
     */
    protected function resolveIdLocale(string $localeName): ?int
    {
        if (isset($this->idLocaleByLocaleName[$localeName])) {
            return $this->idLocaleByLocaleName[$localeName];
        }

        $localeEntity = SpyLocaleQuery::create()->filterByLocaleName($localeName)->findOne();

        if ($localeEntity === null) {
            return null;
        }

        $idLocale = $localeEntity->getIdLocale();
        $this->idLocaleByLocaleName[$localeName] = $idLocale;

        return $idLocale;
    }
}
