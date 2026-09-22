<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Semantic;

interface EmbeddingClientInterface
{
    /**
     * Specification:
     * - Embeds a single piece of text into a dense vector using whatever embedding backend the
     *   implementation talks to (a self-hosted model server for now — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Semantic\TextEmbeddingsInferenceClient} — an
     *   external embedding API for a future implementation). The interface itself carries no assumption
     *   about which.
     * - Throws {@see \SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException} on any
     *   failure to produce a vector — connection failure, timeout, non-success response, or a malformed
     *   response body. Deliberately never returns an empty array or null as a fallback: whether/how to
     *   degrade gracefully (e.g. falling back to lexical-only scoring) is a decision for the caller, not
     *   this client.
     *
     * @api
     *
     * @param string $text
     *
     * @throws \SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException
     *
     * @return array<int, float>
     */
    public function embed(string $text): array;
}
