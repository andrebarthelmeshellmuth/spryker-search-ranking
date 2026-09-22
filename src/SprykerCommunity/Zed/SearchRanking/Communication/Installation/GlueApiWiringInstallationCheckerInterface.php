<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Installation;

interface GlueApiWiringInstallationCheckerInterface
{
    /**
     * Specification:
     * - Establishes whether `randomImpact` will actually appear on a `GET /catalog-search` Glue response.
     *   Two independent things have to both hold and only the FIRST is guaranteed by this package's own
     *   composer install: (1) the additive schema merge ran — `Generated\Api\Storefront\CatalogSearchStorefrontResource`
     *   has a `getRandomImpact()` accessor (a composer PATH-REPOSITORY install can silently fail this even
     *   with everything else correct, because Symfony's `Finder` does not descend into symlinked
     *   directories without `Pyz\Glue\ApiPlatformSymlinkFix\{SchemaFinder,ValidationSchemaFinder}` — see
     *   search-debug's README, "Glue REST API"); (2) a project-level Provider override actually copies the
     *   value into the response at request time — the merged schema only describes SHAPE.
     * - Neither half is ever a FAILURE: a project that does not run a Glue Storefront application at all is
     *   a legitimate, common configuration. A missing/incomplete step (2) after step (1) succeeded is a
     *   WARNING, since that project almost certainly intended `randomImpact` to work.
     * - Returns already-worded strings for the caller to render: `messages` are the `✓` lines to print in
     *   order (stopping at the first unmet step), `warnings` carries the single warning when a step is
     *   unmet. The caller decides only presentation — see
     *   {@see \SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole::checkGlueApiWiring()},
     *   the only consumer today.
     * - Never throws.
     *
     * @return array{messages: array<int, string>, warnings: array<int, string>}
     */
    public function check(): array;
}
