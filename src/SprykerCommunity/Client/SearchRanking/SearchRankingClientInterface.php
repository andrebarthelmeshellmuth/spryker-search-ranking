<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;

interface SearchRankingClientInterface
{
    /**
     * Specification:
     * - Used only by the calibration feature. Fires the calibration query for $searchTerm directly
     *   against Elasticsearch (bypassing `Client\Catalog`/`Client\Search`, which are unusable from Zed in
     *   this shop — see {@see \SprykerCommunity\Client\SearchRanking\Search\CalibrationSearcherInterface}
     *   for why), and returns each matched product's raw text-relevance score, up to $limit.
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
    public function getCalibrationScores(string $searchTerm, string $storeName, string $localeName, int $limit): array;

    /**
     * Specification:
     * - Probes the live search engine's actual capabilities directly (never a version-string comparison)
     *   for a fixed set of constructs this package uses today or could use in a future phase — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityCheckerInterface} for the
     *   full probe methodology.
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer
     */
    public function checkEngineCompatibility(): SearchRankingEngineCompatibilityTransfer;
}
