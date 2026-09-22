<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Search\PageIndexMap;
use Spryker\Client\Kernel\AbstractBundleConfig;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

class SearchRankingConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Whether specificity-aware relevance weighting is active for this project. **Override THIS method
     *   in your project's `Pyz\Client\SearchRanking\SearchRankingConfig` to turn it on** — this class is a
     *   real, Locator-resolved `AbstractBundleConfig`, the only layer this flag is genuinely
     *   project-overridable at. {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig::isSpecificityWeightingEnabled()}
     *   below is a plain hardcoded `return false;`, not overridable by any project class, despite an
     *   earlier docblock/README revision claiming otherwise — it only supplies this method's own default
     *   when a project hasn't overridden it.
     * - Read via {@see \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface::isSpecificityWeightingEnabled()}
     *   by any code that needs to ask without duplicating this resolution, and directly via
     *   `$this->getFactory()->getConfig()->isSpecificityWeightingEnabled()` by
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   itself.
     *
     * @api
     */
    public function isSpecificityWeightingEnabled(): bool
    {
        return SharedSearchRankingConfig::isSpecificityWeightingEnabled();
    }

    /**
     * Specification:
     * - Maps each Elasticsearch page-index field the real search query searches to the SEARCH-TIME
     *   analyzer name declared for it in this project's own `page.json` schema — used to force
     *   `_termvectors` to tokenize the same way the real query does (see
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface}, which
     *   otherwise defaults to a field's INDEX-time analyzer instead).
     * - Defaults to the two standard Spryker product-search fields (`full-text`, `full-text-boosted`)
     *   mapped to Elasticsearch/OpenSearch's built-in `standard` analyzer — safe on a vanilla install, but
     *   almost certainly WRONG for a project with its own custom search-time analyzer (e.g. one adding
     *   synonym handling or edge-ngram matching): **override this method in your project's
     *   `Pyz\Client\SearchRanking\SearchRankingConfig` to match your own `page.json`'s `search_analyzer`
     *   value per field**, the same way `isSpecificityWeightingEnabled()` above is meant to be overridden.
     *
     * @api
     *
     * @return array<string, string>
     */
    public function getSpecificityProbeFieldSearchAnalyzers(): array
    {
        return [
            PageIndexMap::FULL_TEXT => 'standard',
            PageIndexMap::FULL_TEXT_BOOSTED => 'standard',
        ];
    }

    /**
     * Specification:
     * - Base URL of the self-hosted Text Embeddings Inference (TEI) service, used by this Client layer's
     *   own {@see \SprykerCommunity\Client\SearchRanking\Semantic\TextEmbeddingsInferenceClient} at
     *   query time. Client-layer code cannot reach `Zed\SearchRanking\SearchRankingConfig` (a different
     *   layer's Locator-resolved config, not reachable from Yves/Client request context) — this is the
     *   Client-layer's OWN copy of the same setting, kept in sync by reading the same environment
     *   variable {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getEmbeddingServiceUrl()}
     *   reads, so a project only has to set `SEARCH_RANKING_EMBEDDING_SERVICE_URL` once for both layers.
     *
     * @api
     */
    public function getEmbeddingServiceUrl(): string
    {
        $envUrl = getenv('SEARCH_RANKING_EMBEDDING_SERVICE_URL');

        return $envUrl !== false ? $envUrl : 'http://embeddings:80';
    }

    /**
     * Specification:
     * - Identifier of the embedding model this Client layer requests query-time embeddings for, and the
     *   cache-key namespace {@see \SprykerCommunity\Client\SearchRanking\Semantic\SemanticQueryEmbeddingCache}
     *   uses — must match {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::getEmbeddingModelId()}
     *   (the offline job's own model id), or a query vector and a product's stored vector would silently
     *   come from two different embedding spaces.
     *
     * @api
     */
    public function getEmbeddingModelId(): string
    {
        return 'BAAI/bge-base-en-v1.5';
    }

    /**
     * BGE's own documented convention: QUERY-side text gets this instruction prefix prepended before
     * embedding (contrastively trained to make query/passage pairs align better this way); PASSAGE side
     * (product name+description, offline job) gets no prefix at all. Getting this backwards silently
     * degrades retrieval quality without erroring — see this package's README.
     *
     * @api
     */
    public function getEmbeddingQueryInstructionPrefix(): string
    {
        return 'Represent this sentence for searching relevant passages: ';
    }
}
