<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Query;

use Elastica\Query\AbstractQuery;
use Elastica\Query\FunctionScore;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

interface FunctionScoreBuilderInterface
{
    /**
     * Returns null when the configuration yields no usable signal terms (no active metrics,
     * all weights zero, or invalid metric names).
     *
     * @param \Elastica\Query\AbstractQuery $wrappedQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     */
    public function build(
        AbstractQuery $wrappedQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): ?FunctionScore;
}
