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
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;

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
     * $queryContextTransfer, when given AND {@see \Generated\Shared\Transfer\SearchRankingQueryContextTransfer::getIsIdentifierMatch()}
     * is `true` (this query's search string is a recognized product identifier — see
     * {@see \SprykerCommunity\Client\SearchRanking\Intent\SkuIdentifierAnalyzer}), forces the EFFECTIVE
     * alpha used inside the built script to `1.0` (pure lexical), regardless of what
     * `$configurationTransfer->getAlpha()` says — an identifier query wants exact lexical precision;
     * blending in semantic similarity for it only ever hurts (see this package's own measured hybrid-search
     * findings). Omitting this parameter (the 3-argument call every existing caller — this package's own
     * plugin before this change, and `search-ranking-optimizer`'s `RankEvalRunner`, which is NOT part of
     * this pass's scope — already makes) is byte-identical to today's behavior: no override.
     *
     * @param \Elastica\Query\AbstractQuery $wrappedQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<int, float>|null $queryVector
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer|null $queryContextTransfer
     */
    public function build(
        AbstractQuery $wrappedQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        ?array $queryVector = null,
        ?SearchRankingQueryContextTransfer $queryContextTransfer = null,
    ): ?FunctionScore;
}
