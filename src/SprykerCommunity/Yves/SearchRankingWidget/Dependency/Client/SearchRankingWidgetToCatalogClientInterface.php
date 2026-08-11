<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchRankingWidget\Dependency\Client;

interface SearchRankingWidgetToCatalogClientInterface
{
    /**
     * @param string $searchString
     * @param array<string, mixed> $requestParameters
     *
     * @return array<string, mixed>
     */
    public function catalogSearch(string $searchString, array $requestParameters): array;
}
