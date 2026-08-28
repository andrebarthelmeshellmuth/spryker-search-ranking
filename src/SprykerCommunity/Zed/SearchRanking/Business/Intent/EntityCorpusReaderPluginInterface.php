<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

/**
 * The write-side extension seam for {@see SuggestIndexEntityLookupRebuilder} — the Zed-console-driven
 * counterpart of {@see \SprykerCommunity\Client\SearchRanking\Intent\MsearchProbeRegistrarPluginInterface}
 * on the query-time (Client) side. A project (or a future first-party analyzer) that wants a NEW intent
 * entity type — e.g. a manufacturer part number, a color family — implements this interface and registers
 * it via {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingDependencyProvider::PLUGINS_ENTITY_CORPUS_READER}
 * instead of forking this package to add a new `match()` arm.
 *
 * This package's own {@see SkuCorpusReader}, {@see BrandCorpusReader}, and {@see CategoryCorpusReader} are
 * the shipped default stack — they implement this interface too (see each class's own docblock), so a
 * project extending the plugin array on top of them changes nothing about the built-in `sku`/`brand`/
 * `category` types.
 */
interface EntityCorpusReaderPluginInterface
{
    /**
     * Specification:
     * - Returns this plugin's own entity-type identifier — the SAME string
     *   {@see \SprykerCommunity\Client\SearchRanking\Intent\SuggestIndexEntityLookup} is queried with at
     *   query time, and the `--type` value `search-ranking:entity-lookup:suggest-index:rebuild` accepts.
     *   Namespace a project-specific type (e.g. `myproject_manufacturer_part_number`) so it cannot collide
     *   with `sku`/`brand`/`category` or another project's own type.
     * - Called once per rebuild by {@see SuggestIndexEntityLookupRebuilder} to route a `--type` argument to
     *   the matching plugin — MUST be a stable, constant value (no per-call randomness).
     *
     * @api
     */
    public function getEntityType(): string;

    /**
     * Specification:
     * - Every distinct, non-empty term this plugin's entity type contributes for `$idStore`, scoped to
     *   `$idLocale` when the plugin's own term source is locale-scoped (a plugin that has no locale
     *   dimension — e.g. a SKU — simply ignores `$idLocale`).
     * - `$idStore` is always a REAL, resolved store id here — unlike some corpus readers' own public
     *   methods (e.g. {@see SkuCorpusReaderInterface::getSkus()}), which additionally support `null` for
     *   "the whole catalog" on a different call path, {@see SuggestIndexEntityLookupRebuilder} always
     *   iterates one store at a time and never calls this method with anything but a concrete id.
     * - MUST apply the same active/sellable exclusion this package's shipped readers apply (see each
     *   built-in reader's own docblock) — a term reachable ONLY through a delisted/inactive/unsellable
     *   product must not appear here, mirroring how the main product-page index already excludes such
     *   products.
     * - Reads directly from the source of truth (Propel, an external system, ...), never from the search
     *   index itself — this is a full-catalog rebuild command, not a publish-pipeline consumer.
     *
     * @api
     *
     * @param int $idStore
     * @param int|null $idLocale
     *
     * @return array<int, string>
     */
    public function getTerms(int $idStore, ?int $idLocale): array;
}
