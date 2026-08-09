<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchRanking\Plugin;

use Spryker\Shared\PermissionExtension\Dependency\Plugin\PermissionPluginInterface;

/**
 * Grants visibility into the storefront search results page's random-impact simulation (a "-X/+X
 * position" badge per product showing how the ranking would differ with the configured random
 * tie-breaker metric's weight set to 0). Deliberately its OWN permission, not a reuse of
 * search-debug's `SeeSearchDebugInfoPermissionPlugin` — this package stays independently installable
 * from search-debug, with zero code coupling either direction. A project wanting the same admins to see
 * both should grant both permissions to the same role; see this package's README.
 *
 * For Zed & Client PermissionDependencyProvider::getPermissionPlugins() registration.
 */
class SeeSearchRankingRandomImpactPermissionPlugin implements PermissionPluginInterface
{
    /**
     * @var string
     */
    public const KEY = 'SeeSearchRankingRandomImpactPermissionPlugin';

    public function getKey(): string
    {
        return static::KEY;
    }
}
