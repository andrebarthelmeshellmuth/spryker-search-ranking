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
     *
     * @return bool
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
}
