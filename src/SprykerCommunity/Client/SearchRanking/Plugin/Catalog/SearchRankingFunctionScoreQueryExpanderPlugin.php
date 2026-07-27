<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Plugin\Catalog;

use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Generated\Shared\Search\PageIndexMap;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Spryker\Client\Kernel\AbstractPlugin;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryExpanderPluginInterface;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;
use Spryker\Client\SearchExtension\Dependency\Plugin\SearchStringGetterInterface;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 */
class SearchRankingFunctionScoreQueryExpanderPlugin extends AbstractPlugin implements QueryExpanderPluginInterface
{
    /**
     * @var string
     */
    protected const PARAMETER_SEARCH_STRING = 'q';

    /**
     * Elastica query parameter holding the source-field whitelist, when one was set.
     *
     * @var string
     */
    protected const QUERY_PARAM_SOURCE = '_source';

    /**
     * {@inheritDoc}
     * - Wraps the search query in a function_score combining text relevance with the weighted, normalized
     *   business signals from the product documents' `scores` field, via a saturating blend:
     *   relevanceWeight * (_score / (_score + relevanceSaturationPoint))
     *     + (1 - relevanceWeight) * (sum of weight * signal) — see `FunctionScoreBuilder` for the full
     *   rationale (including why an older, unbounded `(1 + sqrt(_score)) * (...)` formula was replaced).
     * - Metric weights, relevanceWeight, and relevanceSaturationPoint all come from the ranking
     *   configuration in key-value storage (synced from Zed).
     * - **Entropy-aware relevance weighting (opt-in, OFF by default)**: when
     *   {@see \SprykerCommunity\Client\SearchRanking\SearchRankingConfig::isEntropyWeightingEnabled()}
     *   is enabled, the configured `relevanceWeight` is replaced with a per-query value derived from ONE
     *   ADDITIONAL lightweight probe query — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightCalculator} for the full
     *   mechanism and this package's README for the rationale. Left disabled, this plugin fires exactly
     *   the one query it always has.
     * - Also adds the `scores` field to the query's source whitelist (when one is set), so consumers —
     *   the search-debug overlay's business-signal breakdown, client-side re-ranking — can read each
     *   hit's normalized signals without another round trip.
     * - Leaves the query untouched when there is no search string (category/browse pages), when no
     *   ranking configuration is synchronized, or when no active metric has a non-zero weight.
     *
     * @api
     *
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     * @param array<string, mixed> $requestParameters
     *
     * @return \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface
     */
    public function expandQuery(QueryInterface $searchQuery, array $requestParameters = []): QueryInterface
    {
        if ($this->getSearchString($searchQuery, $requestParameters) === '') {
            return $searchQuery;
        }

        $query = $searchQuery->getSearchQuery();

        if (!($query instanceof Query)) {
            return $searchQuery;
        }

        $configurationTransfer = $this->getFactory()->getSearchRankingStorageClient()->findRankingConfiguration();

        if ($configurationTransfer === null) {
            return $searchQuery;
        }

        $wrappedQuery = $query->getQuery();

        if (!($wrappedQuery instanceof AbstractQuery)) {
            return $searchQuery;
        }

        $configurationTransfer = $this->applyEntropyWeighting($configurationTransfer, $wrappedQuery);

        $functionScore = $this->getFactory()->createFunctionScoreBuilder()->build(
            $wrappedQuery,
            $configurationTransfer,
        );

        if ($functionScore === null) {
            return $searchQuery;
        }

        $query->setQuery($functionScore);
        $this->addScoresToSourceWhitelist($query);

        return $searchQuery;
    }

    /**
     * OFF by default (see {@see \SprykerCommunity\Client\SearchRanking\SearchRankingConfig::isEntropyWeightingEnabled()}) —
     * returns the configuration transfer unchanged, firing no additional query, unless a project has
     * explicitly opted in.
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param \Elastica\Query\AbstractQuery $baseQuery
     *
     * @return \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer
     */
    protected function applyEntropyWeighting(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        AbstractQuery $baseQuery,
    ): SearchRankingConfigurationStorageTransfer {
        if (!$this->getFactory()->getConfig()->isEntropyWeightingEnabled()) {
            return $configurationTransfer;
        }

        $entropyDerivedRelevanceWeight = $this->getFactory()->createEntropyWeightCalculator()->calculateRelevanceWeight(
            $baseQuery,
            $configurationTransfer,
        );

        return (clone $configurationTransfer)->setRelevanceWeight($entropyDerivedRelevanceWeight);
    }

    /**
     * A query without a source whitelist returns the full `_source` (scores included) already; only an
     * existing whitelist needs the field appended.
     *
     * @param \Elastica\Query $query
     *
     * @return void
     */
    protected function addScoresToSourceWhitelist(Query $query): void
    {
        if (!$query->hasParam(static::QUERY_PARAM_SOURCE)) {
            return;
        }

        $source = (array)$query->getParam(static::QUERY_PARAM_SOURCE);

        if (in_array(PageIndexMap::SCORES, $source, true)) {
            return;
        }

        $source[] = PageIndexMap::SCORES;
        $query->setSource($source);
    }

    /**
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     * @param array<string, mixed> $requestParameters
     *
     * @return string
     */
    protected function getSearchString(QueryInterface $searchQuery, array $requestParameters): string
    {
        if ($searchQuery instanceof SearchStringGetterInterface) {
            return trim((string)$searchQuery->getSearchString());
        }

        return trim((string)($requestParameters[static::PARAMETER_SEARCH_STRING] ?? ''));
    }
}
