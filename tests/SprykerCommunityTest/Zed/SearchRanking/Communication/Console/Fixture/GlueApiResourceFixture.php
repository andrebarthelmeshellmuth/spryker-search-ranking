<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Console\Fixture;

/**
 * Stands in for the real generator-produced `Generated\Api\Storefront\CatalogSearchStorefrontResource`
 * once the schema merge has happened — {@see \SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole::checkGlueApiWiring()}
 * only calls `class_exists()`/`method_exists()`, so a same-shaped fixture with a `getRandomImpact()`
 * accessor is enough.
 */
class GlueApiResourceFixture
{
    /**
     * @return array<string, mixed>
     */
    public function getRandomImpact(): array
    {
        return [];
    }
}
