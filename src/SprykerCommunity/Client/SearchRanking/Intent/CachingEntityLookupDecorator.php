<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

use SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface;

/**
 * @internal Wraps a {@see BatchableEntityLookupInterface} so it can be handed to
 * {@see SkuIdentifierAnalyzer}/{@see BrandAnalyzer}/{@see CategoryAnalyzer} — every one of which calls
 * {@see EntityLookupInterface::exists()} synchronously, some in a loop over several candidate windows —
 * WITHOUT changing any of their internals or {@see QueryAnalyzerInterface}'s own single-pass contract.
 *
 * The trick: a caller that already knows every candidate term an analyzer is about to check (see
 * {@see \SprykerCommunity\Client\SearchRanking\SearchRankingFactory::createBatchedEntityLookupOverrides()},
 * which enumerates exactly the same {@see \SprykerCommunity\Shared\SearchRanking\Intent\QueryWindowExtractor}
 * windows {@see BrandAnalyzer}/{@see CategoryAnalyzer} themselves scan) can pre-register ALL of those
 * probes onto ONE shared batcher, fire it once, and hand this decorator to the analyzer instead of the raw
 * lookup. Every `exists()` call the analyzer then makes is answered from the already-fetched batch — zero
 * additional network calls — as long as the term was among the ones pre-registered.
 *
 * A term OUTSIDE the pre-registered set (the decorator's own cache genuinely has nothing for it — e.g. a
 * caller other than the batched orchestration itself) degrades to firing the inner lookup's own standalone
 * `exists()` request — correct, just not batched, the same graceful-degradation discipline every other
 * best-effort signal in this package already follows. This should never actually happen on the path
 * {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
 * drives (it always pre-registers every window the built-in analyzers will ever look at), but a THIRD-PARTY
 * `QueryAnalyzerInterface` plugin reusing this same decorator for an arbitrary term is a real possibility
 * this fallback exists for.
 */
class CachingEntityLookupDecorator implements EntityLookupInterface
{
    /**
     * @param \SprykerCommunity\Client\SearchRanking\Intent\BatchableEntityLookupInterface $innerLookup
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param string $probeKeyPrefix
     */
    public function __construct(
        protected BatchableEntityLookupInterface $innerLookup,
        protected MsearchProbeBatcherInterface $batcher,
        protected string $probeKeyPrefix,
    ) {
    }

    /**
     * Pre-registers `$term`'s exists-probe onto the shared batcher — must be called for every candidate
     * term BEFORE the batcher's `execute()` runs, or that term's later {@see exists()} call falls back to
     * an uncached, standalone request instead of reading the shared batch.
     *
     * @param string $term
     */
    public function registerProbe(string $term): void
    {
        $normalizedTerm = EntityTermNormalizer::normalize($term);

        if ($normalizedTerm === '') {
            return;
        }

        $this->batcher->registerProbe(
            $this->buildProbeKey($normalizedTerm),
            $this->innerLookup->getIndexName(),
            $this->innerLookup->buildBatchExistsProbeRequest($term),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $term
     */
    public function exists(string $term): bool
    {
        $normalizedTerm = EntityTermNormalizer::normalize($term);

        if ($normalizedTerm === '') {
            return false;
        }

        $responseData = $this->batcher->getResponseFor($this->buildProbeKey($normalizedTerm));

        if ($responseData !== null) {
            return $this->innerLookup->parseBatchExistsProbeResponse($responseData);
        }

        // Not part of the pre-registered batch (see class docblock) — safe, uncached fallback.
        return $this->innerLookup->exists($term);
    }

    /**
     * {@inheritDoc}
     *
     * Suggest-completion is out of scope for batching in this pass — delegated straight to the inner
     * lookup, same as any standalone caller would get.
     *
     * @api
     *
     * @param string $prefix
     * @param int $limit
     *
     * @return array<int, string>
     */
    public function suggest(string $prefix, int $limit): array
    {
        return $this->innerLookup->suggest($prefix, $limit);
    }

    /**
     * @param string $normalizedTerm
     */
    protected function buildProbeKey(string $normalizedTerm): string
    {
        return $this->probeKeyPrefix . ':' . $normalizedTerm;
    }
}
