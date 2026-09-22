<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

interface SkuCorpusReaderInterface
{
    /**
     * Specification:
     * - Every distinct, non-empty `abstract_sku` and `concrete_sku` value in the live catalog, scoped to
     *   one store when `$idStore` is given (a product must be assigned to that store via
     *   `spy_product_abstract_store` — its concrete products are included via their own abstract's
     *   assignment), or the WHOLE catalog when `$idStore` is `null`.
     * - EXCLUDES a delisted/inactive product: `concrete_sku` is only included for a concrete with
     *   `spy_product.is_active = true`; `abstract_sku` is only included when the abstract has at least one
     *   such active concrete — see {@see SkuCorpusReader::getActiveIdProductAbstracts()} for the exact rule
     *   (mirrors the main `page` index's own publish-time exclusion).
     * - Reads directly from Propel (`spy_product_abstract`/`spy_product`/`spy_product_abstract_store`),
     *   never from the search index — this is a full-catalog rebuild command, not a publish-pipeline
     *   consumer.
     *
     * @param int|null $idStore
     *
     * @return array<int, string>
     */
    public function getSkus(?int $idStore): array;
}
