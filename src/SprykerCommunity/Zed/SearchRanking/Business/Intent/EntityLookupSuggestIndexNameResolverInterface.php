<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

interface EntityLookupSuggestIndexNameResolverInterface
{
    /**
     * Specification:
     * - Resolves the entity-lookup OpenSearch index name for `$storeName`, using the SAME
     *   `{prefix}_{store}_{sourceIdentifier}` scheme the Client-side resolver uses.
     *
     * @param string $storeName
     */
    public function resolveIndexName(string $storeName): string;
}
