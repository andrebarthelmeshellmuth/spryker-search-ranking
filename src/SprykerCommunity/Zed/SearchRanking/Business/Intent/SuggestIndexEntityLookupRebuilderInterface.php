<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

interface SuggestIndexEntityLookupRebuilderInterface
{
    /**
     * Specification:
     * - Full rebuild of Pass 2's OpenSearch `completion`-suggester-backed entity dictionary
     *   ({@see \SprykerCommunity\Client\SearchRanking\Intent\SuggestIndexEntityLookup}) for one entity
     *   `$type` — `sku`, `brand`, `category`, or a project-registered
     *   {@see EntityCorpusReaderPluginInterface}'s own type; any `$type` no registered plugin's
     *   {@see EntityCorpusReaderPluginInterface::getEntityType()} matches writes nothing and returns an
     *   empty array.
     * - Creates the index (if missing) and replaces every existing document of `$type` in it — see
     *   {@see SuggestIndexEntityLookupIndexerInterface} for the exact lifecycle.
     * - Same `$filterStoreName`/`$filterLocaleName` filtering contract as
     *   {@see EntityLookupRebuilderInterface::rebuild()}.
     *
     * @param string $type
     * @param string|null $filterStoreName
     * @param string|null $filterLocaleName
     *
     * @return array<string, int> Store name => number of documents written for it.
     */
    public function rebuild(string $type, ?string $filterStoreName, ?string $filterLocaleName): array;
}
