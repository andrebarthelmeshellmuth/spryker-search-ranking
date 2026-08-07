<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;
use Spryker\Client\Kernel\AbstractClient;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculatorInterface;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 */
class SearchRankingClient extends AbstractClient implements SearchRankingClientInterface
{
    protected ?SearchRankingSpecificityWeightingResultTransfer $lastSpecificityWeightingResult = null;

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
     * @param \Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer|null $specificityWeightingResult
     */
    public function rememberLastSpecificityWeightingResult(?SearchRankingSpecificityWeightingResultTransfer $specificityWeightingResult): void
    {
        $this->lastSpecificityWeightingResult = $specificityWeightingResult;
    }

    /**
     * {@inheritDoc}
     */
    public function getLastSpecificityWeightingResult(): ?SearchRankingSpecificityWeightingResultTransfer
    {
        return $this->lastSpecificityWeightingResult;
    }
}
