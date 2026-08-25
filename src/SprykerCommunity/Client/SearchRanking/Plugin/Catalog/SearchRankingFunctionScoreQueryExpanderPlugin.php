<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Plugin\Catalog;

use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Generated\Shared\Search\PageIndexMap;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Spryker\Client\Kernel\AbstractPlugin;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryExpanderPluginInterface;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;
use Spryker\Client\SearchExtension\Dependency\Plugin\SearchStringGetterInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException;

/**
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface getClient()
 */
class SearchRankingFunctionScoreQueryExpanderPlugin extends AbstractPlugin implements QueryExpanderPluginInterface
{
    /**
     * @var string
     */
    protected const PARAMETER_SEARCH_STRING = 'q';

    /**
     * Elastica query parameter holding the source-field whitelist, when one was set.
     *
     * @var string
     */
    protected const QUERY_PARAM_SOURCE = '_source';

    /**
     * {@inheritDoc}
     * - Wraps the search query in a function_score combining text relevance with the weighted, normalized
     *   business signals from the product documents' `scores` field, via a saturating blend:
     *   relevanceWeight * (_score / (_score + relevanceSaturationPoint))
     *     + (1 - relevanceWeight) * (sum of weight * signal) — see `FunctionScoreBuilder` for the full
     *   rationale.
     * - Metric weights, relevanceWeight, and relevanceSaturationPoint all come from the ranking
     *   configuration in key-value storage (synced from Zed).
     * - **Specificity-aware relevance weighting (opt-in, OFF by default)**: when
     *   {@see \SprykerCommunity\Client\SearchRanking\SearchRankingConfig::isSpecificityWeightingEnabled()}
     *   is enabled, the configured `relevanceWeight` is replaced with a per-query value derived from ONE
     *   ADDITIONAL lightweight `_termvectors` probe (no real catalog query) — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator} for the full
     *   mechanism and this package's README for the rationale. Left disabled, this plugin fires exactly
     *   the one query it always has.
     * - Also adds the `scores` field to the query's source whitelist (when one is set), so consumers —
     *   the search-debug overlay's business-signal breakdown, client-side re-ranking — can read each
     *   hit's normalized signals without another round trip.
     * - Leaves the query untouched when there is no search string (category/browse pages), when no
     *   ranking configuration is synchronized, or when no active metric has a non-zero weight.
     *
     * @api
     *
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     * @param array<string, mixed> $requestParameters
     */
    public function expandQuery(QueryInterface $searchQuery, array $requestParameters = []): QueryInterface
    {
        // Reset unconditionally, before any early return: a stale result from an earlier query in the
        // same request (e.g. a prior facet/autosuggest call this plugin also ran for) must never leak
        // into this one — see SearchRankingClientInterface::rememberLastSpecificityWeightingResult().
        $this->getClient()->rememberLastSpecificityWeightingResult(null);

        $searchString = $this->getSearchString($searchQuery, $requestParameters);

        if ($searchString === '') {
            return $searchQuery;
        }

        $query = $searchQuery->getSearchQuery();

        if (!($query instanceof Query)) {
            return $searchQuery;
        }

        $configurationTransfer = $this->getFactory()->getSearchRankingStorageClient()->findRankingConfiguration(
            $this->getFactory()->getStoreClient()->getCurrentStore()->getNameOrFail(),
            $this->getFactory()->getLocaleClient()->getCurrentLocale(),
        );

        if ($configurationTransfer === null) {
            return $searchQuery;
        }

        $wrappedQuery = $query->getQuery();

        if (!($wrappedQuery instanceof AbstractQuery)) {
            return $searchQuery;
        }

        $configurationTransfer = $this->applySpecificityWeighting($configurationTransfer, $searchString);
        $queryVector = $this->resolveQueryVector($searchString, $configurationTransfer);

        $functionScore = $this->getFactory()->createFunctionScoreBuilder()->build(
            $wrappedQuery,
            $configurationTransfer,
            $queryVector,
        );

        if ($functionScore === null) {
            return $searchQuery;
        }

        $query->setQuery($functionScore);
        $this->addScoresToSourceWhitelist($query);

        return $searchQuery;
    }

    /**
     * OFF by default (see {@see \SprykerCommunity\Client\SearchRanking\SearchRankingConfig::isSpecificityWeightingEnabled()}) —
     * returns the configuration transfer unchanged, firing no additional probe, unless a project has
     * explicitly opted in.
     *
     * Remembers the full {@see \Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer}
     * on this package's own Client (see {@see \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface::rememberLastSpecificityWeightingResult()})
     * whenever specificity weighting is enabled — including when the probe itself found no usable signal
     * and fell back to the configured weight unchanged — so the search-debug overlay can later show the
     * SAME relevanceWeight (and the diagnostics behind it) that this method actually used to build the
     * query, not a stale, independently-fetched configured value.
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param string $searchString
     */
    protected function applySpecificityWeighting(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        string $searchString,
    ): SearchRankingConfigurationStorageTransfer {
        if (!$this->getFactory()->getConfig()->isSpecificityWeightingEnabled()) {
            return $configurationTransfer;
        }

        $specificityWeightingResult = $this->getFactory()->createSpecificityWeightCalculator()->calculateWeightingResult(
            $searchString,
            $configurationTransfer,
        );

        $this->getClient()->rememberLastSpecificityWeightingResult($specificityWeightingResult);

        return (clone $configurationTransfer)->setRelevanceWeight($specificityWeightingResult->getRelevanceWeightOrFail());
    }

    /**
     * Resolves the query's semantic embedding for the hybrid-search blend — cache first, then the live
     * embedding service on a miss. Returns `null` (never throws) whenever no vector can usefully be used:
     * `alpha == 1.0` (the configured default — 100% lexical, no point paying for an embedding that would
     * never be blended in), a cache miss followed by any {@see EmbeddingUnavailableException} (embedding
     * service down/timed out/misconfigured), or a malformed cached value. `FunctionScoreBuilder::build()`
     * degrades to exactly today's lexical-only formula whenever this returns `null` — this is the
     * mandatory graceful-degradation path, not an afterthought: a shopper must never see a 500 or an empty
     * result set just because the (optional, best-effort) embedding service is unreachable.
     *
     * This package deliberately has no logging convention anywhere else (verified: no PSR logger is
     * injected or used by any other class here) — an embedding failure is therefore swallowed silently,
     * consistent with how every other best-effort/optional signal in this plugin already behaves (e.g.
     * a missing ranking configuration also just steps aside without logging).
     *
     * @param string $searchString
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return array<int, float>|null
     */
    protected function resolveQueryVector(
        string $searchString,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): ?array {
        $alpha = $configurationTransfer->getAlpha();

        // A null alpha (no explicit setAlpha() call) is treated the same as the documented default of
        // 1.0 — see FunctionScoreBuilder::buildTextComponent()'s own matching guard.
        if ($alpha === null || $alpha >= 1.0) {
            return null;
        }

        $modelId = $this->getFactory()->getConfig()->getEmbeddingModelId();
        $embeddingCache = $this->getFactory()->createSemanticQueryEmbeddingCache();

        $cachedVector = $embeddingCache->get($searchString, $modelId);

        if ($cachedVector !== null) {
            return $cachedVector;
        }

        $queryText = $this->getFactory()->getConfig()->getEmbeddingQueryInstructionPrefix() . $searchString;

        try {
            $vector = $this->getFactory()->createEmbeddingClient()->embed($queryText);
        } catch (EmbeddingUnavailableException) {
            return null;
        }

        $embeddingCache->set($searchString, $modelId, $vector);

        return $vector;
    }

    /**
     * A query without a source whitelist returns the full `_source` (scores included) already; only an
     * existing whitelist needs the field appended.
     *
     * @param \Elastica\Query $query
     */
    protected function addScoresToSourceWhitelist(Query $query): void
    {
        if (!$query->hasParam(static::QUERY_PARAM_SOURCE)) {
            return;
        }

        $source = $query->getParam(static::QUERY_PARAM_SOURCE);

        // Elastica's own setSource() legally accepts a bool too: `true` already means "return the full
        // _source" (nothing to whitelist), and `false` means the caller explicitly disabled _source --
        // neither should be blindly cast to array. (array)false silently becomes [] (which this method
        // would then populate with just 'scores', re-enabling a source the caller explicitly turned off)
        // and (array)true becomes [true], which setSource() would reject alongside a string field name.
        if (is_bool($source)) {
            return;
        }

        if (in_array(PageIndexMap::SCORES, $source, true)) {
            return;
        }

        $source[] = PageIndexMap::SCORES;
        $query->setSource($source);
    }

    /**
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     * @param array<string, mixed> $requestParameters
     */
    protected function getSearchString(QueryInterface $searchQuery, array $requestParameters): string
    {
        if ($searchQuery instanceof SearchStringGetterInterface) {
            return trim((string)$searchQuery->getSearchString());
        }

        return trim((string)($requestParameters[static::PARAMETER_SEARCH_STRING] ?? ''));
    }
}
