<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Embedding;

interface EmbeddingGeneratorInterface
{
    /**
     * Specification:
     * - Iterates product abstracts per store/locale (mirroring
     *   {@see \SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizerInterface::randomizeIfActive()}'s
     *   own store×locale fan-out), pulls each one's name + description directly from Propel, and, unless
     *   the text is unchanged since the last run (`text_hash` match), calls
     *   {@see \SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingClientInterface::embed()} and
     *   upserts the resulting vector into `spy_search_ranking_embedding`.
     * - A failure embedding ONE product (embedding service unreachable/timeout/malformed response) is
     *   logged and skipped — it never aborts the run for the remaining products.
     *
     * @api
     *
     * @param string|null $filterStoreName Restrict to this store only. Null processes every store.
     * @param string|null $filterLocaleName Restrict to this locale only. Null processes every locale
     * available for the selected store(s).
     *
     * @return array{generated: int, skipped: int, failed: int, failures: array<int, string>} `failures`
     * maps `idProductAbstract` to the failure message, for products that could not be embedded.
     */
    public function generate(?string $filterStoreName = null, ?string $filterLocaleName = null): array;
}
