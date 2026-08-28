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
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use Spryker\Client\Kernel\AbstractPlugin;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryExpanderPluginInterface;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;
use Spryker\Client\SearchExtension\Dependency\Plugin\SearchStringGetterInterface;
use SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException;
use SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyExecutionMode;

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
     * - **Navigational relevance-weight shift (Intent-Aware Alpha, Pass 3 — inert by default)**: after
     *   specificity weighting, {@see applyNavigationalShift()} additively shifts `relevanceWeight` again
     *   when the query's brand/category analyzers detected a match — see
     *   {@see \SprykerCommunity\Client\SearchRanking\Search\NavigationalRelevanceWeightShiftCalculator}.
     *   Runs unconditionally, but both shift magnitudes default to `0.0`, so it changes nothing unless a
     *   project deliberately configures a nonzero shift.
     * - Also adds the `scores` field to the query's source whitelist (when one is set), so consumers —
     *   the search-debug overlay's business-signal breakdown, client-side re-ranking — can read each
     *   hit's normalized signals without another round trip.
     * - Leaves the query untouched when there is no search string (category/browse pages), when no
     *   ranking configuration is synchronized, or when no active metric has a non-zero weight.
     * - **Ranking strategy seam (v2 Phase 3)**: the `function_score` above is produced by the
     *   {@see \SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyInterface} resolved for this
     *   query (see {@see \SprykerCommunity\Client\SearchRanking\SearchRankingFactory::resolveRankingStrategy()}).
     *   With no project strategy registered, that is always
     *   {@see \SprykerCommunity\Client\SearchRanking\Strategy\AdaptiveFormulaStrategy}, which delegates
     *   verbatim to `FunctionScoreBuilder` — byte-identical to before the seam existed. Only a body-only
     *   strategy is auto-applied here; an active out-of-band strategy makes this plugin leave the query
     *   body untouched (a dedicated out-of-band execution path, not yet built, runs it).
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
        // into this one — see SearchRankingClientInterface::rememberQueryContext().
        $this->getClient()->rememberQueryContext(null);

        $searchString = $this->getSearchString($searchQuery, $requestParameters);

        if ($searchString === '') {
            return $searchQuery;
        }

        $query = $searchQuery->getSearchQuery();

        if (!($query instanceof Query)) {
            return $searchQuery;
        }

        $storeName = $this->getFactory()->getStoreClient()->getCurrentStore()->getNameOrFail();
        $localeName = $this->getFactory()->getLocaleClient()->getCurrentLocale();

        $configurationTransfer = $this->getFactory()->getSearchRankingStorageClient()->findRankingConfiguration(
            $storeName,
            $localeName,
        );

        if ($configurationTransfer === null) {
            return $searchQuery;
        }

        $wrappedQuery = $query->getQuery();

        if (!($wrappedQuery instanceof AbstractQuery)) {
            return $searchQuery;
        }

        [$queryContextTransfer, $configurationTransfer] = $this->buildQueryContext(
            $searchString,
            $storeName,
            $localeName,
            $configurationTransfer,
        );
        $configurationTransfer = $this->applyNavigationalShift($configurationTransfer, $queryContextTransfer);
        $this->getClient()->rememberQueryContext($queryContextTransfer);

        $queryVector = $this->resolveQueryVector($searchString, $configurationTransfer);

        $rankingStrategy = $this->getFactory()->resolveRankingStrategy($queryContextTransfer);

        if ($rankingStrategy->getExecutionMode() !== RankingStrategyExecutionMode::MODE_BODY_ONLY) {
            // TODO out-of-band strategy execution path (v2 Phase 5): an out-of-band strategy (neural
            // rerank second pass, ML inference, `search_pipeline` URL param, `_plugins/*`) cannot be
            // applied from a query-expander plugin — Spryker\Client\SearchElasticsearch\Search\Search::
            // executeQuery() runs `$index->search($query->getSearchQuery())` and never forwards
            // `$options`, so nothing outside the request body can be set here. Leave the query body
            // untouched; the (not-yet-built) out-of-band path is responsible for running this strategy.
            // The query context is still remembered above, so that path can pick the resolved strategy up.
            return $searchQuery;
        }

        $functionScore = $rankingStrategy->build(
            $wrappedQuery,
            $configurationTransfer,
            $queryVector,
            $queryContextTransfer,
        );

        if ($functionScore === null) {
            return $searchQuery;
        }

        $query->setQuery($functionScore);
        $this->addScoresToSourceWhitelist($query);

        return $searchQuery;
    }

    /**
     * Prefix namespacing specificity weighting's own probe key(s) on the shared
     * {@see \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface} batch built here —
     * distinct from every entity-lookup override's own `entity:<type>:` prefix (see
     * {@see \SprykerCommunity\Client\SearchRanking\SearchRankingFactory::createBatchedEntityLookupOverrides()})
     * so two collaborators registering probes on the same batcher never collide.
     *
     * @var string
     */
    protected const PROBE_KEY_PREFIX_SPECIFICITY = 'specificity';

    /**
     * Builds this ONE query's {@see \Generated\Shared\Transfer\SearchRankingQueryContextTransfer} and,
     * alongside it, the (possibly specificity-adjusted) configuration transfer to actually build the
     * function_score with — restructured (Pass 4) into a single register/execute/consume cycle over ONE
     * shared {@see \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface}:
     *
     * 1. **Register phase**: {@see \SprykerCommunity\Client\SearchRanking\SearchRankingFactory::createBatchedEntityLookupOverrides()}
     *    pre-registers every sku/brand/category entity-lookup probe (ES-backed unconditionally — see
     *    {@see \SprykerCommunity\Client\SearchRanking\Intent\SuggestIndexEntityLookup}, this package's only
     *    entity-lookup implementation); specificity weighting's own probe registers alongside it, ONLY when
     *    {@see \SprykerCommunity\Client\SearchRanking\SearchRankingConfig::isSpecificityWeightingEnabled()}
     *    is on; every project-registered
     *    {@see \SprykerCommunity\Client\SearchRanking\Intent\MsearchProbeRegistrarPluginInterface} (see
     *    {@see \SprykerCommunity\Client\SearchRanking\SearchRankingDependencyProvider::PLUGINS_MSEARCH_PROBE_REGISTRAR})
     *    registers alongside both — empty by default, so a no-op today.
     * 2. **One `execute()` call** — fires exactly ONE `_msearch` bundling everything registered above (the
     *    entity-lookup probes plus, when specificity weighting is enabled, its probe too), or NOTHING AT
     *    ALL when nothing was registered (e.g. a blank search string) — see
     *    `MsearchProbeBatcherInterface::execute()`'s own no-empty-batch guarantee.
     * 3. **Consume phase**: the active {@see \SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface}
     *    stack runs against a fresh transfer for this ONE query — never a shared/reused instance across
     *    analyzers or requests — each one only ever WRITING its own signal field(s), reading the batch
     *    through a {@see \SprykerCommunity\Client\SearchRanking\Intent\CachingEntityLookupDecorator} where
     *    one was pre-registered (see `QueryAnalyzerInterface`'s own docblock for the full
     *    independence-trap rationale this preserves unchanged). Specificity weighting then reads its own
     *    probe back the same way.
     *
     * Every existing behavioral contract stays intact: specificity weighting still fires NO probe at all
     * when disabled; a component on the in-memory/KV tier never participates in the batch (it has no ES
     * probe to register in the first place); the whole mechanism is a complete no-op — zero ES calls beyond
     * the main query — in today's actual default configuration.
     *
     * @param string $searchString
     * @param string $storeName
     * @param string $localeName
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return array{0: \Generated\Shared\Transfer\SearchRankingQueryContextTransfer, 1: \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer}
     */
    protected function buildQueryContext(
        string $searchString,
        string $storeName,
        string $localeName,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): array {
        $batcher = $this->getFactory()->createMsearchProbeBatcher();
        $entityLookupOverrides = $this->getFactory()->createBatchedEntityLookupOverrides($batcher, $searchString);

        $isSpecificityWeightingEnabled = $this->getFactory()->getConfig()->isSpecificityWeightingEnabled();
        $specificityWeightCalculator = $this->getFactory()->createSpecificityWeightCalculator();

        if ($isSpecificityWeightingEnabled) {
            $specificityWeightCalculator->registerProbes($batcher, static::PROBE_KEY_PREFIX_SPECIFICITY, $searchString);
        }

        // Built once, before the register phase, and reused unchanged through the consume phase below — at
        // register time no analyzer/plugin has written a signal field yet, so this is the SAME plain
        // request-scoped transfer {@see \SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface::analyze()}
        // and {@see \SprykerCommunity\Client\SearchRanking\Intent\MsearchProbeRegistrarPluginInterface::registerProbes()}
        // both read.
        $queryContextTransfer = (new SearchRankingQueryContextTransfer())
            ->setSearchString($searchString)
            ->setStoreName($storeName)
            ->setLocaleName($localeName)
            // Explicit, not left to a transfer default: this codebase's transfer generator does not
            // actually initialize `default="false"` on a plain (non-collection) property — see the
            // transfer.xml comment for `isIdentifierMatch`.
            ->setIsIdentifierMatch(false);

        foreach ($this->getFactory()->getMsearchProbeRegistrarPlugins() as $msearchProbeRegistrarPlugin) {
            $msearchProbeRegistrarPlugin->registerProbes($batcher, $queryContextTransfer);
        }

        // ONE round trip for everything registered above — or none at all when nothing was.
        $batcher->execute();

        foreach ($this->getFactory()->getQueryAnalyzers($entityLookupOverrides) as $queryAnalyzer) {
            $queryContextTransfer = $queryAnalyzer->analyze($queryContextTransfer);
        }

        if (!$isSpecificityWeightingEnabled) {
            return [$queryContextTransfer, $configurationTransfer];
        }

        // Remembers the full SearchRankingSpecificityWeightingResultTransfer on the query context —
        // including when the probe itself found no usable signal and fell back to the configured weight
        // unchanged — so the search-debug overlay can later show the SAME relevanceWeight (and the
        // diagnostics behind it) that this method actually used to build the query, not a stale,
        // independently-fetched configured value.
        $specificityWeightingResult = $specificityWeightCalculator->consumeProbes(
            $batcher,
            static::PROBE_KEY_PREFIX_SPECIFICITY,
            $searchString,
            $configurationTransfer,
        );

        $queryContextTransfer->setSpecificityWeightingResult($specificityWeightingResult);
        $configurationTransfer = (clone $configurationTransfer)->setRelevanceWeight($specificityWeightingResult->getRelevanceWeightOrFail());

        return [$queryContextTransfer, $configurationTransfer];
    }

    /**
     * Intent-Aware Alpha, Pass 3: composes the brand/category navigational shifts (see
     * {@see \SprykerCommunity\Client\SearchRanking\Search\NavigationalRelevanceWeightShiftCalculator})
     * on top of whatever `relevanceWeight` {@see buildQueryContext()} already produced (or the
     * plain configured value, when specificity weighting is disabled or produced no shift). Runs
     * UNCONDITIONALLY — no separate `isXxxEnabled()` config gate — because both shift magnitudes default
     * to `0.0` (see the transfer's own docblock), making this a no-op by default exactly the same way
     * `alpha` is value-gated rather than boolean-gated.
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    protected function applyNavigationalShift(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        SearchRankingQueryContextTransfer $queryContextTransfer,
    ): SearchRankingConfigurationStorageTransfer {
        $effectiveRelevanceWeight = $this->getFactory()->createNavigationalRelevanceWeightShiftCalculator()->calculateEffectiveRelevanceWeight(
            (float)$configurationTransfer->getRelevanceWeight(),
            $configurationTransfer,
            $queryContextTransfer,
        );

        return (clone $configurationTransfer)->setRelevanceWeight($effectiveRelevanceWeight);
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
