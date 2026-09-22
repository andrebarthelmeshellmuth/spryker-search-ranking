<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking;

use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToStorageClientBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToAclFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToDataImportFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToLocaleFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSearchRankingStorageFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSynchronizationFacadeBridge;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToTranslatorFacadeBridge;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FACADE_EVENT = 'FACADE_EVENT';

    /**
     * @var string
     */
    public const FACADE_SEARCH_RANKING_STORAGE = 'FACADE_SEARCH_RANKING_STORAGE';

    /**
     * @var string
     */
    public const CLIENT_SEARCH_RANKING = 'CLIENT_SEARCH_RANKING';

    /**
     * @var string
     */
    public const FACADE_STORE = 'FACADE_STORE';

    /**
     * Used by {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupRebuilder}
     * to resolve a `--locale` option's name to an `idLocale` for category-name scoping.
     *
     * @var string
     */
    public const FACADE_LOCALE = 'FACADE_LOCALE';

    /**
     * @var string
     */
    public const FACADE_SYNCHRONIZATION = 'FACADE_SYNCHRONIZATION';

    /**
     * @var string
     */
    public const FACADE_TRANSLATOR = 'FACADE_TRANSLATOR';

    /**
     * @var string
     */
    public const FACADE_DATA_IMPORT = 'FACADE_DATA_IMPORT';

    /**
     * Used ONLY by `search-ranking:check-installation`, to report whether anybody other than a root-style
     * admin can reach this package's Zed pages. Nothing on the request path consults it — Zed access
     * control is enforced by Spryker's own Acl module, exactly as for every other module.
     *
     * @var string
     */
    public const FACADE_ACL = 'FACADE_ACL';

    /**
     * Zed writing directly to key-value storage (Valkey/Redis) — the same bridge shape
     * `spryker-community/search-signals`' own Zed layer already uses for the same reason: `entity-lookup:
     * rebuild` writes the SKU dictionary `SkuIdentifierAnalyzer` (Client layer) reads at query time, and
     * there is no other channel from a Zed console into that same KV store.
     *
     * @var string
     */
    public const CLIENT_STORAGE = 'CLIENT_STORAGE';

    /**
     * Specification:
     * - Additional {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface}
     *   instances a project wants {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\SuggestIndexEntityLookupRebuilder}
     *   to run, layered ON TOP of this package's own built-in default ({@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\SkuCorpusReader},
     *   {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\BrandCorpusReader},
     *   {@see \SprykerCommunity\Zed\SearchRanking\Business\Intent\CategoryCorpusReader} — see
     *   {@see \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingBusinessFactory::getEntityCorpusReaders()}).
     *   Empty by default — the SAME "empty array, project extends" pattern this package's Client-layer
     *   {@see \SprykerCommunity\Client\SearchRanking\SearchRankingDependencyProvider::PLUGINS_QUERY_ANALYZER}
     *   already uses for its own optional plugin stack. This is the seam a project plugs a NEW entity type
     *   into without forking this package's own `sku`/`brand`/`category` readers.
     *
     * @var string
     */
    public const PLUGINS_ENTITY_CORPUS_READER = 'PLUGINS_ENTITY_CORPUS_READER';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    #[\Override]
    public function provideBusinessLayerDependencies(Container $container): Container
    {
        $container = parent::provideBusinessLayerDependencies($container);
        $container = $this->addEventFacade($container);
        $container = $this->addSearchRankingClient($container);
        $container = $this->addStoreFacade($container);
        $container = $this->addStorageClient($container);
        $container = $this->addLocaleFacade($container);
        $container = $this->addEntityCorpusReaderPlugins($container);

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addEntityCorpusReaderPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_ENTITY_CORPUS_READER, fn (): array => $this->getEntityCorpusReaderPlugins());

        return $container;
    }

    /**
     * @return array<\SprykerCommunity\Zed\SearchRanking\Business\Intent\EntityCorpusReaderPluginInterface>
     */
    protected function getEntityCorpusReaderPlugins(): array
    {
        return [];
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addLocaleFacade(Container $container): Container
    {
        $container->set(static::FACADE_LOCALE, fn (Container $container) => new SearchRankingToLocaleFacadeBridge(
            $container->getLocator()->locale()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_STORAGE, fn (Container $container) => new SearchRankingToStorageClientBridge(
            $container->getLocator()->storage()->client(),
        ));

        return $container;
    }

    /**
     * The storage facade is a Communication-layer dependency only (console command); the Business
     * layer must stay free of it to avoid a circular module dependency with SearchRankingStorage. The
     * event, synchronization, translator, and data-import facades are ALL Communication-layer-only for
     * the same reason: `search-ranking:check-installation` (Communication layer) needs them purely to
     * diagnose whether the project registered this package's various wiring points correctly (README
     * steps 4, 6, 11, 12); none of them belong on the real business logic's dependency surface. The event
     * facade is bound here IN ADDITION TO the Business layer above because it's also used there to
     * actually trigger the ranking-configuration change event, not just to diagnose it.
     *
     * @param \Spryker\Zed\Kernel\Container $container
     */
    #[\Override]
    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);
        $container = $this->addEventFacade($container);
        $container = $this->addSynchronizationFacade($container);
        $container = $this->addTranslatorFacade($container);
        $container = $this->addDataImportFacade($container);
        $container = $this->addAclFacade($container);

        return $this->addSearchRankingStorageFacade($container);
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSearchRankingStorageFacade(Container $container): Container
    {
        $container->set(static::FACADE_SEARCH_RANKING_STORAGE, fn (Container $container) => new SearchRankingToSearchRankingStorageFacadeBridge(
            $container->getLocator()->searchRankingStorage()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addEventFacade(Container $container): Container
    {
        $container->set(static::FACADE_EVENT, fn (Container $container) => new SearchRankingToEventFacadeBridge(
            $container->getLocator()->event()->facade(),
        ));

        return $container;
    }

    /**
     * Used by the engine-compatibility check (`search-ranking:check-compatibility`) to probe the live
     * search engine's capabilities directly, bypassing `Client\Catalog`/`Client\Search` (which are
     * unusable from Zed in this shop).
     *
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSearchRankingClient(Container $container): Container
    {
        $container->set(static::CLIENT_SEARCH_RANKING, fn (Container $container) => new SearchRankingToSearchRankingClientBridge(
            $container->getLocator()->searchRanking()->client(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addStoreFacade(Container $container): Container
    {
        $container->set(static::FACADE_STORE, fn (Container $container) => new SearchRankingToStoreFacadeBridge(
            $container->getLocator()->store()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSynchronizationFacade(Container $container): Container
    {
        $container->set(static::FACADE_SYNCHRONIZATION, fn (Container $container) => new SearchRankingToSynchronizationFacadeBridge(
            $container->getLocator()->synchronization()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addTranslatorFacade(Container $container): Container
    {
        $container->set(static::FACADE_TRANSLATOR, fn (Container $container) => new SearchRankingToTranslatorFacadeBridge(
            $container->getLocator()->translator()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addDataImportFacade(Container $container): Container
    {
        $container->set(static::FACADE_DATA_IMPORT, fn (Container $container) => new SearchRankingToDataImportFacadeBridge(
            $container->getLocator()->dataImport()->facade(),
        ));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addAclFacade(Container $container): Container
    {
        $container->set(static::FACADE_ACL, fn (Container $container) => new SearchRankingToAclFacadeBridge(
            $container->getLocator()->acl()->facade(),
        ));

        return $container;
    }
}
