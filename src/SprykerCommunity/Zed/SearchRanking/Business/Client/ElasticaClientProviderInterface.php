<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Client;

use Elastica\Client;

interface ElasticaClientProviderInterface
{
    /**
     * Specification:
     * - A fully Zed-native `Elastica\Client` — no HTTP/request-session context involved, unlike the
     *   Client-layer `Client\Search`/`Client\Catalog` facades, which crash when called outside a real
     *   request (see this package's README).
     */
    public function getClient(): Client;
}
