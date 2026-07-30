<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Spryker\Client\Kernel\AbstractClient;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 */
class SearchRankingClient extends AbstractClient implements SearchRankingClientInterface
{
    /**
     * @var \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult|null
     */
    protected ?EntropyWeightingResult $lastEntropyWeightingResult = null;

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
     * @param \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult|null $entropyWeightingResult
     *
     * @return void
     */
    public function rememberLastEntropyWeightingResult(?EntropyWeightingResult $entropyWeightingResult): void
    {
        $this->lastEntropyWeightingResult = $entropyWeightingResult;
    }

    /**
     * {@inheritDoc}
     *
     * @return \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult|null
     */
    public function getLastEntropyWeightingResult(): ?EntropyWeightingResult
    {
        return $this->lastEntropyWeightingResult;
    }
}
