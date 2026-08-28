<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

interface BrandCorpusReaderInterface
{
    /**
     * Specification:
     * - Every distinct, non-empty real `brand` product-attribute value (see
     *   `data/import/common/common/product_attribute_key.csv` — `brand` is a plain product attribute, the
     *   same one already flowing into the main page index's facets) in the live catalog, scoped to one
     *   store when `$idStore` is given, or the WHOLE catalog when `$idStore` is `null`.
     * - EXCLUDES a brand value reachable ONLY through a delisted/inactive product abstract — see
     *   {@see BrandCorpusReader::getActiveIdProductAbstracts()} for the exact rule (mirrors the main `page`
     *   index's own publish-time exclusion).
     * - Reads `spy_product_abstract_localized_attributes.attributes` (a JSON-encoded map) directly via
     *   Propel — never from the search index, same "full-catalog rebuild, not a publish-pipeline consumer"
     *   discipline as {@see SkuCorpusReaderInterface::getSkus()}. Scans every locale's row (brand values are
     *   not expected to differ per locale in this schema, but the storage itself is locale-scoped) — a
     *   caller that needs one locale's set only should filter the result, not this method.
     *
     * @param int|null $idStore
     *
     * @return array<int, string>
     */
    public function getBrands(?int $idStore): array;
}
