<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

interface SuggestIndexEntityLookupIndexerInterface
{
    /**
     * Specification:
     * - Creates `$indexName` from this package's own `Shared/SearchRanking/Schema/entity-lookup.json`
     *   mapping if it does not already exist. A no-op if it does — safe to call on every rebuild.
     * - Deliberately a SIMPLER create-if-missing lifecycle, NOT the `spryker-community/search-index-alias`
     *   blue-green alias-swap mechanism that package's own README documents for the heavy `page`/`merchant`
     *   indices: that package's rebuild is built around bulk-loading FROM the publish/sync `spy_*_search`
     *   tables and mirroring a live RabbitMQ publish exchange during the rebuild window — this entity-lookup
     *   index has neither (it is populated by reading Propel tables directly, on a fixed administrative
     *   schedule, not a live publish pipeline), so wiring it into that mechanism would mean inventing a
     *   publish/sync pipeline for it first. Documented as a follow-up, not attempted here — see this
     *   package's README.
     *
     * @param string $indexName
     */
    public function ensureIndexExists(string $indexName): void;

    /**
     * Specification:
     * - Full replace (not incremental) of every document of `$type` in `$indexName`: deletes every existing
     *   document with `type: $type` via `_delete_by_query`, then bulk-indexes one document per entry in
     *   `$terms` (`{term, termNormalized, type}` — see `Schema/entity-lookup.json`). Other types' documents
     *   in the same physical index are untouched.
     * - Refreshes the index once at the end so the new documents are immediately visible to a query fired
     *   right after this call returns (this package's own live-verification, and any test, would otherwise
     *   see stale/empty results on OpenSearch's default ~1s refresh interval).
     *
     * @param string $indexName
     * @param string $type
     * @param array<int, string> $terms
     *
     * @return int Number of documents indexed.
     */
    public function replaceTerms(string $indexName, string $type, array $terms): int;

    /**
     * Specification:
     * - Targeted, idempotent single/few-term write path for the event-pipeline incremental sync
     *   ({@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityLookupIncrementalSyncer}) — NOT a
     *   scaled-down {@see replaceTerms()}: it never touches any document but the ones for `$terms`, so it
     *   is safe to call once per published product without disturbing the rest of `$type`'s corpus.
     * - Each term is written under a deterministic id (derived from `$type` + its normalized form), so
     *   calling this twice for the same term is a no-op overwrite, never a duplicate document — a full
     *   {@see replaceTerms()} rebuild still finds and replaces these documents too, since they carry the
     *   same `type`/`term`/`termNormalized` fields a rebuild-created document would; only the document id
     *   scheme differs, and nothing reads or depends on that id.
     * - Refreshes the index once at the end, same as {@see replaceTerms()}.
     *
     * @param string $indexName
     * @param string $type
     * @param array<int, string> $terms
     *
     * @return int Number of documents written.
     */
    public function upsertTerms(string $indexName, string $type, array $terms): int;

    /**
     * Specification:
     * - Targeted removal counterpart to {@see upsertTerms()}: deletes every document of `$type` whose
     *   `termNormalized` matches one of `$terms` (case/whitespace-insensitive, mirroring
     *   {@see \SprykerCommunity\Client\SearchRanking\Intent\EntityTermNormalizer::normalize()}), regardless
     *   of whether that document was originally written by {@see replaceTerms()} or {@see upsertTerms()}.
     *   Other terms of the same `$type` are untouched.
     * - Refreshes the index once at the end, same as {@see replaceTerms()}.
     *
     * @param string $indexName
     * @param string $type
     * @param array<int, string> $terms
     *
     * @return int Number of documents deleted.
     */
    public function removeTerms(string $indexName, string $type, array $terms): int;
}
