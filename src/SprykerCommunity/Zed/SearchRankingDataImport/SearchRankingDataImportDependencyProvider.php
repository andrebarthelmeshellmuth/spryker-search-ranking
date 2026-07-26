<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport;

use Spryker\Zed\DataImport\DataImportDependencyProvider;

/**
 * Empty on purpose, not dead weight: Spryker resolves a module's container by this exact
 * per-namespace class name, so it must exist for the parent's wiring (propel connection, flysystem,
 * touch/event facades — used by SearchRankingDataImportBusinessFactory) to reach this module at all.
 */
class SearchRankingDataImportDependencyProvider extends DataImportDependencyProvider
{
}
