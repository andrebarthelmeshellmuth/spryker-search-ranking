<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface;

/**
 * The extension seam for a project (or a future first-party analyzer) that needs its OWN Elasticsearch/
 * OpenSearch probe to ride the SAME shared `_msearch` batch this package already fires for specificity
 * weighting and sku/brand/category entity lookups — see
 * {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin::buildQueryContext()}
 * for the full register/execute/consume orchestration this plugs into.
 *
 * Deliberately a SEPARATE interface from {@see QueryAnalyzerInterface}, not an extension of it: most
 * analyzers have no probe of their own to register at all (they either read a KV/in-process signal, or
 * consume one of this package's own pre-registered entity-lookup overrides), so forcing every analyzer to
 * implement an empty `registerProbes()` would be the wrong default. A plugin that needs BOTH — register a
 * probe here, then read the result back — implements this interface AND a
 * {@see QueryAnalyzerInterface}/its own consumer, the same two-phase split
 * {@see \SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator} and
 * {@see CachingEntityLookupDecorator} already use internally.
 *
 * Registered via {@see \SprykerCommunity\Client\SearchRanking\SearchRankingDependencyProvider::PLUGINS_MSEARCH_PROBE_REGISTRAR}
 * — empty by default, the same "empty array, project extends" pattern this package already uses for
 * {@see QueryAnalyzerInterface}'s own plugin stack.
 */
interface MsearchProbeRegistrarPluginInterface
{
    /**
     * Specification:
     * - Queues zero or more probes onto `$batcher`, in the SAME register phase this package's own
     *   built-in collaborators (specificity weighting, sku/brand/category entity lookups) register theirs
     *   in — before the plugin's single shared `$batcher->execute()` call. Use a probe key namespaced to
     *   this plugin (e.g. `myplugin:<term>`) so it cannot collide with any other collaborator's own keys on
     *   the same shared batcher.
     * - Reads only `searchString`/`storeName`/`localeName` off `$queryContextTransfer` — the same
     *   request-scoped fields {@see QueryAnalyzerInterface::analyze()} reads, for the same reason (no other
     *   analyzer/plugin's signal has been written to the transfer yet at register time).
     * - Fires nothing itself. Never throws: a probe that can't even be BUILT (not fired — building a query
     *   body is pure data construction) should simply register nothing rather than error.
     *
     * @api
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param \Generated\Shared\Transfer\SearchRankingQueryContextTransfer $queryContextTransfer
     */
    public function registerProbes(MsearchProbeBatcherInterface $batcher, SearchRankingQueryContextTransfer $queryContextTransfer): void;
}
