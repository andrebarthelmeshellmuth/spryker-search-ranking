<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Spryker\Client\Kernel\AbstractBundleConfig;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

class SearchRankingConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Whether entropy-aware relevance weighting is active — see
     *   {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig::isEntropyWeightingEnabled()},
     *   the real source of truth (moved here from a Client-only flag so Zed can read the same value).
     *   **Override `Pyz\Shared\SearchRanking\SearchRankingConfig` now, not this class** — this method
     *   stays only so existing callers (e.g.
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin})
     *   don't need to change.
     *
     * @api
     *
     * @return bool
     */
    public function isEntropyWeightingEnabled(): bool
    {
        return SharedSearchRankingConfig::isEntropyWeightingEnabled();
    }
}
