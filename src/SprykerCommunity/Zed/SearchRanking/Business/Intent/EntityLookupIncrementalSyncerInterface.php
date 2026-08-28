<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

interface EntityLookupIncrementalSyncerInterface
{
    /**
     * Specification:
     * - The event-pipeline near-live sync mode for Pass 2's entity-lookup OpenSearch index — the
     *   incremental counterpart to {@see SuggestIndexEntityLookupRebuilderInterface::rebuild()}'s full
     *   cron/manual rebuild. Backs {@see \SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingEntityLookupSyncPlugin},
     *   fired once per product-page-search publish batch.
     * - For each `$idProductAbstracts` entry and each STORE it is actually assigned to
     *   (`spy_product_abstract_store`): for every registered {@see IncrementalEntityCorpusReaderPluginInterface}
     *   (a plugin implementing only the base {@see EntityCorpusReaderPluginInterface} is SKIPPED here — it
     *   has no incremental seam and relies entirely on the periodic full rebuild instead), reads that
     *   product's own current terms and either upserts them (product is active/sellable) or removes them
     *   (product is not) — see {@see IncrementalEntityCorpusReaderPluginInterface} for the exact
     *   active/shared-term rules applied.
     * - A no-op for an id with no registered incremental plugin, no assigned store, or no terms — never
     *   throws for a product that simply has nothing to contribute.
     * - Idempotent and safe to call with the same ids more than once (e.g. a retried publish) — see
     *   {@see SuggestIndexEntityLookupIndexerInterface::upsertTerms()}/{@see SuggestIndexEntityLookupIndexerInterface::removeTerms()}.
     *
     * @param array<int> $idProductAbstracts
     */
    public function sync(array $idProductAbstracts): void;
}
