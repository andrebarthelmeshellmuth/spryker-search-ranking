<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Debug;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult;

interface ScoreSectionBuilderInterface
{
    /**
     * Returns null when the configuration has no metric weights (nothing to explain).
     *
     * When `$specificityWeightingResult` is given (i.e. specificity weighting actually ran for this
     * query), the "Relevance weight (α)" line and the combination formula use its `relevanceWeight` — the
     * value ACTUALLY applied to this query — instead of `$configurationTransfer`'s statically configured
     * one, so the printed formula stays reproducible-by-eye against the real final score.
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<string, float> $documentScores
     * @param float|null $queryScore
     * @param \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult|null $specificityWeightingResult
     *
     * @return array<string, mixed>|null
     */
    public function build(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        array $documentScores,
        ?float $queryScore,
        ?SpecificityWeightingResult $specificityWeightingResult = null,
    ): ?array;

    /**
     * A second, separate overlay section (title + one line per diagnostic) explaining how specificity
     * weighting arrived at `$specificityWeightingResult`'s `relevanceWeight` — the configured baseline it
     * started from, the normalized specificity the probe measured, that value's signed deviation from the
     * neutral point, the shift magnitude it gets scaled by, the shift that specificity produced (with the
     * full shiftMagnitude/deviation/exponent calculation that produced it), and the resulting effective
     * weight. Only ever called when specificity weighting actually ran for this query.
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightingResult $specificityWeightingResult
     *
     * @return array<string, mixed>
     */
    public function buildSpecificitySection(SpecificityWeightingResult $specificityWeightingResult): array;
}
