<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Plugin\Catalog;

use Elastica\Query;
use Generated\Shared\Search\PageIndexMap;
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
     * - Wraps the search query in a function_score combining text relevance with the weighted,
     *   normalized business signals from the product documents' `scores` field:
     *   (1 + sqrt(_score)) * (sum of weight * signal + floor).
     * - Weights and floor come from the ranking configuration in key-value storage (synced from Zed).
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
    public function expandQuery(QueryInterface $searchQuery, array $requestParameters = [])
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

        $functionScore = $this->getFactory()->createFunctionScoreBuilder()->build(
            $query->getQuery(),
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
