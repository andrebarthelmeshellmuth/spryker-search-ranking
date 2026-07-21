<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Spryker\Client\Kernel\AbstractClient;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 */
class SearchRankingClient extends AbstractClient implements SearchRankingClientInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $searchTerm
     * @param string $storeName
     * @param string $localeName
     * @param int $limit
     *
     * @return array<float>
     */
    public function getCalibrationScores(string $searchTerm, string $storeName, string $localeName, int $limit): array
    {
        return $this->getFactory()
            ->createCalibrationSearcher()
            ->searchScores($searchTerm, $storeName, $localeName, $limit);
    }

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
}
