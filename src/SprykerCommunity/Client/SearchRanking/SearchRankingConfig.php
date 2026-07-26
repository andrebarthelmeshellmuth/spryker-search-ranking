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
     * @var int
     */
    protected const ENTROPY_PROBE_RESULT_SIZE = 10;

    /**
     * @var float
     */
    protected const ENTROPY_WEIGHT_EXPONENT = 1.0;

    /**
     * Specification:
     * - Whether entropy-aware relevance weighting is active. OFF by default — enabling it makes
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   fire ONE ADDITIONAL lightweight Elasticsearch query per live catalog search (a cheap, scores-only
     *   probe against {@see getEntropyProbeResultSize()} candidates) to derive a per-query `relevanceWeight`
     *   instead of using the configured static one. Override this in your project's
     *   `Pyz\Client\SearchRanking\SearchRankingConfig` to turn it on — do not flip this on by editing this
     *   package's own source.
     *
     * @api
     *
     * @return bool
     */
    public function isEntropyWeightingEnabled(): bool
    {
        return false;
    }

    /**
     * Specification:
     * - Number of top-ranked candidates (by raw text-relevance score, before this package's own
     *   function_score wrapper) the entropy probe query fetches to measure the score distribution's
     *   shape. Only `_score` is read — the probe requests no `_source` fields, keeping it cheap.
     *
     * @api
     *
     * @return int
     */
    public function getEntropyProbeResultSize(): int
    {
        return static::ENTROPY_PROBE_RESULT_SIZE;
    }

    /**
     * Specification:
     * - Exponent applied to the normalized entropy before it becomes the effective business-signal
     *   weight (`businessWeight = normalizedEntropy ** exponent`). `1.0` uses the entropy value as-is;
     *   raising it above `1.0` makes the effect kick in only once the score distribution is quite flat;
     *   lowering it below `1.0` makes even a mildly flat distribution push weight toward business
     *   signals sooner. See this package's README for the full rationale.
     *
     * @api
     *
     * @return float
     */
    public function getEntropyWeightExponent(): float
    {
        return static::ENTROPY_WEIGHT_EXPONENT;
    }
}
