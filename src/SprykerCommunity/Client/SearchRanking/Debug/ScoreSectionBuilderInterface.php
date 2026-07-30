<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Debug;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult;

interface ScoreSectionBuilderInterface
{
    /**
     * Returns null when the configuration has no metric weights (nothing to explain).
     *
     * When `$entropyWeightingResult` is given (i.e. entropy weighting actually ran for this query), the
     * "Relevance weight (α)" line and the combination formula use its `relevanceWeight` — the value
     * ACTUALLY applied to this query — instead of `$configurationTransfer`'s statically configured one, so
     * the printed formula stays reproducible-by-eye against the real final score.
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<string, float> $documentScores
     * @param float|null $queryScore
     * @param \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult|null $entropyWeightingResult
     *
     * @return array<string, mixed>|null
     */
    public function build(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        array $documentScores,
        ?float $queryScore,
        ?EntropyWeightingResult $entropyWeightingResult = null,
    ): ?array;

    /**
     * A second, separate overlay section (title + one line per diagnostic) explaining how entropy
     * weighting arrived at `$entropyWeightingResult`'s `relevanceWeight` — the configured baseline it
     * started from, the normalized entropy the probe measured, the shift that entropy produced, and the
     * resulting effective weight. Only ever called when entropy weighting actually ran for this query.
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\EntropyWeightingResult $entropyWeightingResult
     *
     * @return array<string, mixed>
     */
    public function buildEntropySection(EntropyWeightingResult $entropyWeightingResult): array;
}
