<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

/**
 * A generic, reusable seam for collapsing several independent Elasticsearch/OpenSearch probes — a
 * specificity-weighting term-count probe, an entity-lookup exists-check, a corpus doc-count probe — into
 * ONE `_msearch` round trip instead of firing each as its own separate request. Deliberately holds NO
 * knowledge of what any registered probe actually MEANS; every caller is responsible for building its own
 * query body and interpreting its own response slice.
 *
 * Real, measured motivation (not theoretical): 3 sequential `size:0` count-searches against this project's
 * own OpenSearch 1.3.4 cluster measured 24-37ms; the SAME 3 queries bundled into one `_msearch` measured
 * 2-8ms (steady ~2ms) — a real ~10-15x reduction. See this package's README/CHANGELOG for the full
 * measurement session.
 *
 * One instance is meant to live for exactly one "register everything, then fire once" cycle per live
 * request — see {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
 * for the real orchestration. Calling {@see registerProbe()} again after {@see execute()} starts a fresh
 * batch (the previous batch's responses stay retrievable via {@see getResponseFor()} until the next
 * `execute()` call overwrites them), so an instance CAN be reused across more than one register/execute
 * cycle if a caller ever needs that, though today's callers only ever use one cycle per instance.
 */
interface MsearchProbeBatcherInterface
{
    /**
     * Specification:
     * - Queues one probe for the NEXT {@see execute()} call. `$key` is the caller's own identifier for
     *   this probe (namespaced by the caller, e.g. `specificity:gadget` or `entity:brand:topstar`, so two
     *   independent collaborators registering probes on the SAME shared batcher instance never collide) —
     *   used only to route the matching response slice back via {@see getResponseFor()}, never sent to the
     *   engine itself.
     * - `$indexName` may differ per probe — a single `_msearch` batch is NOT required to target only one
     *   index; each probe carries its own header line naming its own index, so specificity's probes (the
     *   `page` index) and an entity-lookup's probes (a separate suggest index) can ride in the same batch.
     * - `$queryBody` is a raw Elasticsearch/OpenSearch query-body array — e.g. `{"size":0,"query":{...}}` —
     *   used verbatim as this probe's `_msearch` body line.
     * - Registering the same `$key` twice before the next `execute()` overwrites the earlier registration
     *   (last write wins) rather than firing it twice.
     *
     * @api
     *
     * @param string $key
     * @param string $indexName
     * @param array<string, mixed> $queryBody
     */
    public function registerProbe(string $key, string $indexName, array $queryBody): void;

    /**
     * Specification:
     * - Fires exactly ONE `_msearch` request bundling every probe registered since construction (or the
     *   previous `execute()` call), then clears the pending-registration queue.
     * - **Never fires an empty `_msearch`**: if nothing was registered, this is a complete no-op — no
     *   network call at all. This is the load-bearing guarantee that makes the whole batching mechanism a
     *   true no-op in today's default configuration (specificity weighting off, brand/category on the
     *   in-memory tier): nothing registers a probe, so `execute()` never fires anything.
     * - Never throws: any request-level failure (connection error, cluster down) degrades to every
     *   registered probe's {@see getResponseFor()} call returning `null` afterward, the same
     *   graceful-degradation discipline every other best-effort signal in this package already follows.
     *   A single probe's OWN failure (e.g. its target index doesn't exist) does not need this fallback —
     *   `_msearch` itself still returns HTTP 200 with a per-item `error` object for that one slice, which
     *   {@see getResponseFor()} still returns to the caller to interpret.
     *
     * @api
     */
    public function execute(): void;

    /**
     * Specification:
     * - Returns the raw response slice for the probe registered under `$key` in the most recently executed
     *   batch — the exact `responses[N]` element `_msearch` returned for that probe, unparsed.
     * - Returns `null` when `$key` was never registered, when `execute()` has not run yet since it was
     *   registered, or when the whole batch failed at the transport level (see {@see execute()}). A `null`
     *   here is NOT the same as "this probe's own query matched nothing" — that case is a normal, non-null
     *   response slice (e.g. `hits.total.value === 0`), which the caller interprets itself.
     *
     * @api
     *
     * @param string $key
     *
     * @return array<string, mixed>|null
     */
    public function getResponseFor(string $key): ?array;
}
