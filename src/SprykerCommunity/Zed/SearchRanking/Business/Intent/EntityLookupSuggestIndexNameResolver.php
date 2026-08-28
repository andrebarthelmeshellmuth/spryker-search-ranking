<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

use Spryker\Shared\SearchElasticsearch\SearchElasticsearchConfig;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;

/**
 * The one place that resolves the entity-lookup OpenSearch index name for a given store, on the Zed side —
 * shared by {@see SuggestIndexEntityLookupRebuilder} (full rebuild) and {@see EntityLookupIncrementalSyncer}
 * (event-pipeline incremental sync) so the two can never drift onto different index names.
 *
 * Same `{prefix}_{store}_{sourceIdentifier}` scheme
 * `SprykerCommunity\Client\SearchRanking\SearchRankingFactory::resolveEntityLookupSuggestIndexName()` uses
 * on the query-time (Client) side — kept as a separate implementation because the Zed side needs to
 * resolve it per-STORE (iterating every configured store, or the specific stores one product belongs to),
 * not for "the current store" a Client-side request always has.
 */
class EntityLookupSuggestIndexNameResolver implements EntityLookupSuggestIndexNameResolverInterface
{
    /**
     * @param \Spryker\Shared\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     */
    public function __construct(protected SearchElasticsearchConfig $searchElasticsearchConfig)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $storeName
     */
    public function resolveIndexName(string $storeName): string
    {
        $indexParameters = [
            $this->searchElasticsearchConfig->getIndexPrefix(),
            $storeName,
            SearchRankingConfig::ENTITY_LOOKUP_SUGGEST_INDEX_SOURCE_IDENTIFIER,
        ];

        return mb_strtolower(implode('_', array_filter($indexParameters)));
    }
}
