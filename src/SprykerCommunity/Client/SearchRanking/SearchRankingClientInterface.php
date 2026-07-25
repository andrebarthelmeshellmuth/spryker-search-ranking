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
