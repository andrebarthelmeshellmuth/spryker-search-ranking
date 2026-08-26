<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

/**
 * OPTIONAL extension of {@see EntityCorpusReaderPluginInterface} — implementing it additionally lets
 * {@see EntityLookupIncrementalSyncer} (the event-pipeline hook backing this package's Pass 2 near-live
 * sync mode) keep a single published product's terms current WITHOUT a full {@see SuggestIndexEntityLookupRebuilder}
 * run. A plugin that implements ONLY {@see EntityCorpusReaderPluginInterface} still works exactly as
 * before for the manual/cron full-rebuild console — it simply never participates in incremental sync, and
 * relies entirely on the periodic full rebuild (cron mode) to stay current. This package's own
 * {@see SkuCorpusReader}, {@see BrandCorpusReader}, and {@see CategoryCorpusReader} all implement this too.
 *
 * The three methods below exist because a SINGLE published product cannot be synced correctly with only
 * {@see EntityCorpusReaderPluginInterface::getTerms()}, which only ever answers for a WHOLE store's active
 * catalog:
 * - {@see getTermsForProductAbstract()} answers "what terms does THIS product currently contribute",
 *   independent of whether it is active right now (the caller decides add vs. remove based on
 *   {@see isProductAbstractActive()} separately).
 * - {@see isProductAbstractActive()} is the same "at least one active, sellable concrete" rule every
 *   shipped reader's {@see EntityCorpusReaderPluginInterface::getTerms()} already applies at the whole-catalog
 *   level, exposed here for one product at a time.
 * - {@see isTermStillUsedElsewhere()} exists because SKU/brand/category terms are NOT all 1:1 with a
 *   product — a SKU is unique to one product, but a brand or category value is typically shared by many.
 *   Deactivating product X must not blindly delete "Bosch" from the corpus if other active products still
 *   carry that same brand.
 */
interface IncrementalEntityCorpusReaderPluginInterface extends EntityCorpusReaderPluginInterface
{
    /**
     * Specification:
     * - Every distinct, non-empty term this plugin's entity type would contribute for `$idProductAbstract`
     *   ALONE, regardless of whether that product is currently active/sellable — the caller decides
     *   whether to upsert or remove these based on {@see isProductAbstractActive()}.
     * - Reads directly from Propel, never from the search index.
     *
     * @api
     *
     * @param int $idProductAbstract
     *
     * @return array<int, string>
     */
    public function getTermsForProductAbstract(int $idProductAbstract): array;

    /**
     * Specification:
     * - Same "at least one active, sellable concrete" rule {@see EntityCorpusReaderPluginInterface::getTerms()}'s
     *   shipped implementations already apply at the whole-catalog level (see e.g.
     *   {@see SkuCorpusReader::getActiveIdProductAbstracts()}), evaluated for exactly ONE product-abstract.
     *
     * @api
     *
     * @param int $idProductAbstract
     *
     * @return bool
     */
    public function isProductAbstractActive(int $idProductAbstract): bool;

    /**
     * Specification:
     * - Whether `$term` (this plugin's entity type) is still contributed by at least one OTHER active,
     *   sellable product-abstract in `$idStore` — i.e. whether it would still be safe to remove `$term`
     *   from the entity-lookup index now that `$idProductAbstract` is no longer active.
     * - MUST exclude `$idProductAbstract` itself from the "still used" check — that product's own
     *   deactivation is the very reason this is being asked.
     * - A plugin whose terms are always 1:1 with a single product-abstract (like SKU) MAY simply always
     *   return `false` here — removal is unconditionally safe for such a type.
     *
     * @api
     *
     * @param string $term
     * @param int $idProductAbstract
     * @param int $idStore
     *
     * @return bool
     */
    public function isTermStillUsedElsewhere(string $term, int $idProductAbstract, int $idStore): bool;
}
