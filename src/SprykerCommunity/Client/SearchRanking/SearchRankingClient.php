<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;
use Spryker\Client\Kernel\AbstractClient;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculatorInterface;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 */
class SearchRankingClient extends AbstractClient implements SearchRankingClientInterface
{
    protected ?SearchRankingQueryContextTransfer $queryContext = null;

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer
    {
        return $this->getFactory()
            ->createEngineCompatibilityChecker()
            ->checkCompatibility();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isSpecificityWeightingEnabled(): bool
    {
        return $this->getFactory()->getConfig()->isSpecificityWeightingEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<string, string>
     */
    public function getSpecificityProbeFieldSearchAnalyzers(): array
    {
        return $this->getFactory()->getConfig()->getSpecificityProbeFieldSearchAnalyzers();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function createFunctionScoreBuilder(): FunctionScoreBuilderInterface
    {
        return $this->getFactory()->createFunctionScoreBuilder();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function createQuerySpecificityCalculator(): QuerySpecificityCalculatorInterface
    {
        return $this->getFactory()->createQuerySpecificityCalculator();
    }

    /**
     * {@inheritDoc}
     *
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer|null $queryContextTransfer
     */
    public function rememberQueryContext(?SearchRankingQueryContextTransfer $queryContextTransfer): void
    {
        $this->queryContext = $queryContextTransfer;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryContext(): ?SearchRankingQueryContextTransfer
    {
        return $this->queryContext;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@see rememberQueryContext()} instead.
     *
     * @param \Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer|null $specificityWeightingResult
     */
    public function rememberLastSpecificityWeightingResult(?SearchRankingSpecificityWeightingResultTransfer $specificityWeightingResult): void
    {
        if ($specificityWeightingResult === null) {
            if ($this->queryContext !== null) {
                $this->queryContext = $this->queryContext->setSpecificityWeightingResult(null);
            }

            return;
        }

        $this->queryContext = ($this->queryContext ?? new SearchRankingQueryContextTransfer())
            ->setSpecificityWeightingResult($specificityWeightingResult);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@see getQueryContext()} instead.
     */
    public function getLastSpecificityWeightingResult(): ?SearchRankingSpecificityWeightingResultTransfer
    {
        return $this->queryContext?->getSpecificityWeightingResult();
    }
}
