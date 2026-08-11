<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchRankingWidget;

use Spryker\Yves\Kernel\AbstractFactory;
use SprykerCommunity\Yves\SearchRankingWidget\Dependency\Client\SearchRankingWidgetToCatalogClientInterface;

class SearchRankingWidgetFactory extends AbstractFactory
{
    public function getCatalogClient(): SearchRankingWidgetToCatalogClientInterface
    {
        return $this->getProvidedDependency(SearchRankingWidgetDependencyProvider::CLIENT_CATALOG);
    }
}
