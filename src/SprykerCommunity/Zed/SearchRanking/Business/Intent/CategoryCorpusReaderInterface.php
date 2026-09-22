<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

interface CategoryCorpusReaderInterface
{
    /**
     * Specification:
     * - Every distinct, non-empty real `spy_category_attribute.name` value in the live catalog, scoped to
     *   one store when `$idStore` is given (via `spy_category_store`), and to one locale when
     *   `$idLocale` is given (`spy_category_attribute.fk_locale`) — `null` for either means "every
     *   store"/"every locale".
     * - EXCLUDES a category reachable ONLY through a delisted/inactive product abstract — see
     *   {@see CategoryCorpusReader::getIdCategoriesWithActiveProducts()} for the exact rule (mirrors the
     *   main `page` index's own publish-time exclusion).
     * - Reads directly from Propel, never from the search index — same "full-catalog rebuild" discipline as
     *   {@see SkuCorpusReaderInterface::getSkus()}.
     *
     * @param int|null $idStore
     * @param int|null $idLocale
     *
     * @return array<int, string>
     */
    public function getCategories(?int $idStore, ?int $idLocale): array;
}
