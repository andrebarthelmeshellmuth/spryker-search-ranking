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
     * - Whether entropy-aware relevance weighting is active for this project. **Override THIS method in
     *   your project's `Pyz\Client\SearchRanking\SearchRankingConfig` to turn it on** — this class is a
     *   real, Locator-resolved `AbstractBundleConfig`, the only layer this flag is genuinely
     *   project-overridable at. {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig::isEntropyWeightingEnabled()}
     *   below is a plain hardcoded `return false;`, not overridable by any project class, despite an
     *   earlier docblock/README revision claiming otherwise — it only supplies this method's own default
     *   when a project hasn't overridden it.
     * - Read via {@see \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface::isEntropyWeightingEnabled()}
     *   by any code that needs to ask without duplicating this resolution, and directly via
     *   `$this->getFactory()->getConfig()->isEntropyWeightingEnabled()` by
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   itself.
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
