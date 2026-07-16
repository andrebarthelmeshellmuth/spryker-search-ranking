<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Debug;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

interface ScoreSectionBuilderInterface
{
    /**
     * Returns null when the configuration has no metric weights (nothing to explain).
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<string, float> $documentScores
     * @param float|null $queryScore
     *
     * @return array<string, mixed>|null
     */
    public function build(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        array $documentScores,
        ?float $queryScore,
    ): ?array;
}
