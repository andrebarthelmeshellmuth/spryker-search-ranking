<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Spryker\Client\Kernel\AbstractBundleConfig;

class SearchRankingConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Whether entropy-aware relevance weighting is active. OFF by default — enabling it makes
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   fire ONE ADDITIONAL lightweight Elasticsearch query per live catalog search (a cheap, scores-only
     *   probe against the Zed-editable probe-result-size setting) to derive a per-query `relevanceWeight`
     *   instead of using the configured static one. Override this in your project's
     *   `Pyz\Client\SearchRanking\SearchRankingConfig` to turn it on — do not flip this on by editing this
     *   package's own source.
     * - Deliberately a code-level flag, not a Zed-editable setting: this is the one switch that decides
     *   whether a second live Elasticsearch query fires on every catalog search at all, so flipping it
     *   requires a project deploy, not just a Zed form save. The probe's own tuning numbers (result size,
     *   weight exponent, shift magnitude) ARE Zed-editable — see `/search-ranking-gui/settings` — once
     *   this flag is on.
     *
     * @api
     *
     * @return bool
     */
    public function isEntropyWeightingEnabled(): bool
    {
        return false;
    }
}
