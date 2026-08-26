<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Client;

use Elastica\Client;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig;

/**
 * Builds the same `Elastica\Client` core's own Zed `SearchElasticsearchBusinessFactory::getElasticsearchClient()`
 * builds — same reasoning and same class shape as `spryker-community/search-index-alias`'s own
 * `ElasticaClientProvider`, which this mirrors deliberately rather than depending on that package (this
 * package must stay independently installable — see this package's README, "Standalone community
 * packages").
 */
class ElasticaClientProvider implements ElasticaClientProviderInterface
{
    /**
     * @param \Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     */
    public function __construct(protected SearchElasticsearchConfig $searchElasticsearchConfig)
    {
    }

    public function getClient(): Client
    {
        return (new ElasticaClientFactory())->createClient(
            $this->searchElasticsearchConfig->getClientConfig(),
        );
    }
}
