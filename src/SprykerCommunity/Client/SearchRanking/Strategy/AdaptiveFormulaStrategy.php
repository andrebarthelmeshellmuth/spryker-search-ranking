<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Strategy;

use Elastica\Query\AbstractQuery;
use Elastica\Query\FunctionScore;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;

/**
 * @internal The default ranking strategy: the adaptive saturating-blend `function_score` that was the
 * one hardcoded pipeline before Phase 3. It is a thin wrapper — the actual query building still lives in
 * {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder}, whose public `build()`
 * signature is untouched. With only this strategy registered (the default), a real search query produces
 * a byte-identical Elasticsearch request to before the strategy seam existed.
 */
class AdaptiveFormulaStrategy implements RankingStrategyInterface
{
    /**
     * Stable identifier `search-ranking-optimizer` keys its parameter space on. Must not change.
     *
     * @var string
     */
    public const NAME = 'adaptive_formula';

    /**
     * @param \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface $functionScoreBuilder
     */
    public function __construct(protected FunctionScoreBuilderInterface $functionScoreBuilder)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * {@inheritDoc}
     *
     * - Returns `true` unconditionally: this is the default/fallback strategy.
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $queryContextTransfer is mandated by RankingStrategyInterface; this strategy supports every query.
     *
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    public function supports(SearchRankingQueryContextTransfer $queryContextTransfer): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getExecutionMode(): string
    {
        return RankingStrategyExecutionMode::MODE_BODY_ONLY;
    }

    /**
     * {@inheritDoc}
     *
     * - Delegates verbatim to {@see \SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder::build()};
     *   returns `null` whenever the builder does (no usable signal terms).
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
    ): ?FunctionScore {
        return $this->functionScoreBuilder->build(
            $wrappedQuery,
            $configurationTransfer,
            $queryVector,
            $queryContextTransfer,
        );
    }
}
