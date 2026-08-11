<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchRankingWidget\Plugin\Router;

use Spryker\Shared\Config\Config;
use Spryker\Yves\Router\Plugin\RouteProvider\AbstractRouteProviderPlugin;
use Spryker\Yves\Router\Route\RouteCollection;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConstants;

/**
 * This package's storefront surface is otherwise pure Twig/SCSS/TS rendered by the project's own SRP
 * template, so this plugin exists solely for the installation-check page — a project that does not opt
 * into {@see SearchRankingConstants::IS_CHECK_INSTALLATION_PAGE_ENABLED} adds no routes by registering it
 * and does not need to register it at all.
 */
class SearchRankingWidgetRouteProviderPlugin extends AbstractRouteProviderPlugin
{
    /**
     * @var string
     */
    public const ROUTE_NAME_CHECK_INSTALLATION = 'search-ranking-widget/check-installation';

    /**
     * @param \Spryker\Yves\Router\Route\RouteCollection $routeCollection
     */
    public function addRoutes(RouteCollection $routeCollection): RouteCollection
    {
        if (!Config::get(SearchRankingConstants::IS_CHECK_INSTALLATION_PAGE_ENABLED, false)) {
            return $routeCollection;
        }

        $checkInstallationRoute = $this->buildRoute('/search-ranking-widget/check-installation', 'SearchRankingWidget', 'CheckInstallation', 'indexAction');
        $routeCollection->add(static::ROUTE_NAME_CHECK_INSTALLATION, $checkInstallationRoute);

        return $routeCollection;
    }
}
