<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Spryker\Client\Kernel\AbstractClient;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 */
class SearchRankingClient extends AbstractClient implements SearchRankingClientInterface
{
    /**
     * @var \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult|null
     */
    protected ?SpecificityWeightingResult $lastSpecificityWeightingResult = null;

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer
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
     *
     * @return bool
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
     * @param \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult|null $specificityWeightingResult
     *
     * @return void
     */
    public function rememberLastSpecificityWeightingResult(?SpecificityWeightingResult $specificityWeightingResult): void
    {
        $this->lastSpecificityWeightingResult = $specificityWeightingResult;
    }

    /**
     * {@inheritDoc}
     *
     * @return \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult|null
     */
    public function getLastSpecificityWeightingResult(): ?SpecificityWeightingResult
    {
        return $this->lastSpecificityWeightingResult;
    }
}
