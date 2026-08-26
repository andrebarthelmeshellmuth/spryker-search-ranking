<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking;

use Spryker\Client\Kernel\AbstractDependencyProvider;
use Spryker\Client\Kernel\Container;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToLocaleClientBridge;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToPermissionClientBridge;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToSearchRankingStorageClientBridge;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStorageClientBridge;
use SprykerCommunity\Client\SearchRanking\Dependency\Client\SearchRankingToStoreClientBridge;

class SearchRankingDependencyProvider extends AbstractDependencyProvider
{
    /**
     * @var string
     */
    public const CLIENT_SEARCH_RANKING_STORAGE = 'CLIENT_SEARCH_RANKING_STORAGE';

    /**
     * @var string
     */
    public const CLIENT_STORE = 'CLIENT_STORE';

    /**
     * @var string
     */
    public const CLIENT_LOCALE = 'CLIENT_LOCALE';

    /**
     * @var string
     */
    public const CLIENT_PERMISSION = 'CLIENT_PERMISSION';

    /**
     * @var string
     */
    public const CLIENT_STORAGE = 'CLIENT_STORAGE';

    /**
     * Specification:
     * - Additional {@see \SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface} instances
     *   a project wants to run, layered ON TOP of this package's own built-in default (today: just
     *   {@see \SprykerCommunity\Client\SearchRanking\Intent\SkuIdentifierAnalyzer} — see
     *   {@see \SprykerCommunity\Client\SearchRanking\SearchRankingFactory::getQueryAnalyzers()}). Empty by
     *   default — the same "empty array, project extends" pattern this codebase already uses for its other
     *   optional plugin stacks. This is the seam a later pass (e.g. a brand/category analyzer, or a
     *   project-specific one) plugs into without touching this package.
     *
     * @var string
     */
    public const PLUGINS_QUERY_ANALYZER = 'PLUGINS_QUERY_ANALYZER';

    /**
     * Specification:
     * - Additional {@see \SprykerCommunity\Client\SearchRanking\Intent\MsearchProbeRegistrarPluginInterface}
     *   instances a project wants to ride the shared `_msearch` batch
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}
     *   builds every request. Empty by default — the SAME "empty array, project extends" pattern this
     *   package already uses for {@see PLUGINS_QUERY_ANALYZER}.
     *
     * @var string
     */
    public const PLUGINS_MSEARCH_PROBE_REGISTRAR = 'PLUGINS_MSEARCH_PROBE_REGISTRAR';

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    #[\Override]
    public function provideServiceLayerDependencies(Container $container): Container
    {
        $container = parent::provideServiceLayerDependencies($container);
        $container = $this->addSearchRankingStorageClient($container);
        $container = $this->addStoreClient($container);
        $container = $this->addLocaleClient($container);
        $container = $this->addPermissionClient($container);
        $container = $this->addStorageClient($container);
        $container = $this->addQueryAnalyzerPlugins($container);
        $container = $this->addMsearchProbeRegistrarPlugins($container);

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addQueryAnalyzerPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_QUERY_ANALYZER, function (): array {
            return $this->getQueryAnalyzerPlugins();
        });

        return $container;
    }

    /**
     * @return array<\SprykerCommunity\Client\SearchRanking\Intent\QueryAnalyzerInterface>
     */
    protected function getQueryAnalyzerPlugins(): array
    {
        return [];
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addMsearchProbeRegistrarPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_MSEARCH_PROBE_REGISTRAR, function (): array {
            return $this->getMsearchProbeRegistrarPlugins();
        });

        return $container;
    }

    /**
     * @return array<\SprykerCommunity\Client\SearchRanking\Intent\MsearchProbeRegistrarPluginInterface>
     */
    protected function getMsearchProbeRegistrarPlugins(): array
    {
        return [];
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addSearchRankingStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_SEARCH_RANKING_STORAGE, fn (Container $container) => new SearchRankingToSearchRankingStorageClientBridge(
            $container->getLocator()->searchRankingStorage()->client(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addStoreClient(Container $container): Container
    {
        $container->set(static::CLIENT_STORE, fn (Container $container) => new SearchRankingToStoreClientBridge(
            $container->getLocator()->store()->client(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addLocaleClient(Container $container): Container
    {
        $container->set(static::CLIENT_LOCALE, fn (Container $container) => new SearchRankingToLocaleClientBridge(
            $container->getLocator()->locale()->client(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addPermissionClient(Container $container): Container
    {
        $container->set(static::CLIENT_PERMISSION, fn (Container $container) => new SearchRankingToPermissionClientBridge(
            $container->getLocator()->permission()->client(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     */
    protected function addStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_STORAGE, fn (Container $container) => new SearchRankingToStorageClientBridge(
            $container->getLocator()->storage()->client(),
        ));

        return $container;
    }
}
