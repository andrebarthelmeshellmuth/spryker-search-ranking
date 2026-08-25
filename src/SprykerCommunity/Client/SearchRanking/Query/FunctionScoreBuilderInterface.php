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
     * $queryVector, when given, is a resolved semantic embedding of the search string (see
     * `SemanticQueryEmbeddingCache`/`EmbeddingClientInterface`); it is only actually used inside the
     * built script when `$configurationTransfer->getAlpha()` is below `1.0` — passing a vector alongside
     * `alpha == 1.0` (the default) has no effect on the resulting script, which stays byte-identical to
     * the pure-lexical formula.
     *
     * @param \Elastica\Query\AbstractQuery $wrappedQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<int, float>|null $queryVector
     */
    public function build(
        AbstractQuery $wrappedQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        ?array $queryVector = null,
    ): ?FunctionScore;
}
