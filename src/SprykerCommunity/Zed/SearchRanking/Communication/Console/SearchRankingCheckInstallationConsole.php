<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Console;

use ArrayObject;
use Elastica\Client;
use Generated\Shared\Transfer\DataImportConfigurationActionTransfer;
use Generated\Shared\Transfer\DataImportConfigurationTransfer;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Kernel\KernelConstants;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Shared\SearchRanking\SearchRankingEvents;
use SprykerCommunity\Shared\SearchRankingStorage\SearchRankingStorageConfig;
use SprykerCommunity\Zed\SearchRankingDataImport\SearchRankingDataImportConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Diagnoses a search-ranking installation.
 *
 * This package's own README installation section has 17 steps, and — same as search-debug's equivalent
 * command — almost every one of them fails SILENTLY when missed: a forgotten DependencyProvider wire-up
 * or an un-run cron produces no error, just a ranking that quietly stays pure text-relevance. This checks
 * every prerequisite reachable from the CLI and names the exact remedy for whatever is wrong.
 *
 * Deliberately honest about its own limits, same posture as
 * {@see \SprykerCommunity\Zed\SearchDebug\Communication\Console\SearchDebugCheckInstallationConsole}: it
 * cannot confirm the Yves-side `function_score` query expander is registered (step 13) or that a live
 * storefront search actually reflects the configured weights — those need a real search request to
 * verify, not a CLI probe. Also distinct from `search-ranking:check-compatibility`: that command asks
 * "does this ENGINE support what the package needs", this one asks "is THIS INSTALLATION wired up
 * correctly" — run both for a full picture.
 *
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory getFactory()
 */
class SearchRankingCheckInstallationConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-ranking:check-installation';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Diagnoses a search-ranking installation: core namespace, sibling console command registration, data-import plugin registration, ranking-configuration publish event listener and sync queue, Zed translations, search engine reachability, page index shape, and configured metrics.';

    /**
     * @var string
     */
    protected const CORE_NAMESPACE = 'SprykerCommunity';

    /**
     * A stable, page-heading-level string from this package's own `data/translation/Zed/en_US.csv` (step 6)
     * — unlikely to be casually reworded, unlike a button label.
     *
     * @var string
     */
    protected const KNOWN_ZED_TRANSLATION_KEY = 'Search Ranking Metrics';

    /**
     * @var string
     */
    protected const KNOWN_ZED_TRANSLATION_LOCALE = 'en_US';

    /**
     * The other console commands this package registers — step 3 of the README's installation section.
     * Checked via {@see \Symfony\Component\Console\Application::has()} rather than anything Config-based:
     * a command only shows up there once `ConsoleDependencyProvider::getConsoleCommands()` actually
     * instantiated it, which is exactly what "was step 3 done" means.
     *
     * @var array<string>
     */
    protected const SIBLING_COMMANDS = [
        'search-ranking:normalize',
        'search-ranking:randomize',
        'search-ranking:check-compatibility',
    ];

    /**
     * @var array<string>
     */
    protected array $failures = [];

    /**
     * @var array<string>
     */
    protected array $warnings = [];

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);

        parent::configure();
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $input is mandated by the Console base class.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkCoreNamespace($output);
        $this->checkSiblingCommandsRegistered($output);
        $this->checkDataImportPluginsRegistered($output);
        $this->checkEventListenerRegistered($output);
        $this->checkSyncQueueRegistered($output);
        $this->checkZedTranslationRegistered($output);
        $this->checkSearchEngine($output);
        $this->checkActiveMetrics($output);

        $output->writeln('');

        foreach ($this->warnings as $warning) {
            $output->writeln(sprintf('<comment>! %s</comment>', $warning));
        }

        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                $output->writeln(sprintf('<error>✗ %s</error>', $failure));
            }

            return static::CODE_ERROR;
        }

        $output->writeln('<info>Everything checkable from the CLI is in place.</info>');
        $output->writeln('Not verifiable from here — these need a real storefront search request, not a CLI probe:');
        $output->writeln('  - the Yves function_score query expander is registered (step 13)');
        $output->writeln('  - the ranking-configuration KV document search-ranking:normalize published is the one Yves actually reads');
        $output->writeln('  - a live search result order actually reflects the configured weights');

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkCoreNamespace(OutputInterface $output): void
    {
        $coreNamespaces = Config::get(KernelConstants::CORE_NAMESPACES, []);

        if (in_array(static::CORE_NAMESPACE, $coreNamespaces, true)) {
            $output->writeln(sprintf('<info>✓</info> core namespace "%s" is registered', static::CORE_NAMESPACE));

            return;
        }

        $this->failures[] = sprintf(
            'Core namespace "%s" is NOT registered. Add it to KernelConstants::CORE_NAMESPACES in config/Shared/config_default.php — without it Spryker cannot resolve any of this package\'s classes.',
            static::CORE_NAMESPACE,
        );
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkSiblingCommandsRegistered(OutputInterface $output): void
    {
        // Spryker's own Console base class declares `@method Application getApplication()` (non-nullable),
        // but the real Symfony\Component\Console\Command::getApplication() it inherits from returns
        // `?Application` — this override reflects the real, narrower-than-inherited-docblock type so the
        // null check below isn't flagged as dead code.
        /** @var \Symfony\Component\Console\Application|null $application */
        $application = $this->getApplication();

        if ($application === null) {
            $this->warnings[] = 'Could not access the console Application instance — skipping sibling command checks.';

            return;
        }

        $missingCommands = [];

        foreach (static::SIBLING_COMMANDS as $commandName) {
            if ($application->has($commandName)) {
                continue;
            }

            $missingCommands[] = $commandName;
        }

        if ($missingCommands === []) {
            $output->writeln(sprintf('<info>✓</info> all %d sibling console commands are registered', count(static::SIBLING_COMMANDS)));

            return;
        }

        $this->failures[] = sprintf(
            'The following console commands are NOT registered: %s. Add them in ConsoleDependencyProvider::getConsoleCommands() (README step 3).',
            implode(', ', $missingCommands),
        );
    }

    /**
     * Verifies the two data-import plugins (README step 4) are registered in
     * `Pyz\Zed\DataImport\DataImportDependencyProvider::getDataImportPlugins()`, via
     * {@see \Spryker\Zed\DataImport\Business\DataImportFacadeInterface::getImportersDumpByConfiguration()}.
     * NOT `listImporters()`/its underlying `ImporterDumper::dump()` — despite reading like the obvious
     * choice, that method only reflects the older `DataImporterCollection`-based registration style (an
     * always-empty collection under this package's plugin-based registration), so it would silently
     * report nothing even on a correctly wired installation. `getImportersDumpByConfiguration()` is the
     * one that actually consults the registered plugin stack for a given import type.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkDataImportPluginsRegistered(OutputInterface $output): void
    {
        $importTypes = [
            SearchRankingDataImportConfig::IMPORT_TYPE_SEARCH_RANKING_METRIC,
            SearchRankingDataImportConfig::IMPORT_TYPE_SEARCH_RANKING_PRODUCT_METRIC,
        ];

        $actions = new ArrayObject();

        foreach ($importTypes as $importType) {
            $actions->append((new DataImportConfigurationActionTransfer())->setDataEntity($importType));
        }

        $configuredImporters = $this->getFactory()->getDataImportFacade()->getImportersDumpByConfiguration(
            (new DataImportConfigurationTransfer())->setActions($actions),
        );

        $missingImportTypes = array_diff($importTypes, array_keys($configuredImporters));

        if ($missingImportTypes === []) {
            $output->writeln(sprintf('<info>✓</info> all %d data-import plugins are registered', count($importTypes)));

            return;
        }

        $this->failures[] = sprintf(
            'The following data-import types are NOT registered: %s. Add them in DataImportDependencyProvider::getDataImportPlugins() (README step 4).',
            implode(', ', $missingImportTypes),
        );
    }

    /**
     * Verifies a listener is registered for {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingEvents::RANKING_CONFIGURATION_CHANGE}
     * (README step 12) via {@see \Spryker\Zed\Event\Business\EventFacadeInterface::dumpEventListener()} —
     * the same project-wide, runtime-merged event map core's own `event:dump:listener` command reads.
     * Unlike step 13's Yves-side query expander, this one IS reachable from a Zed CLI probe: the listener
     * collection lives in the project's `Pyz\Zed\Event\EventDependencyProvider`, resolved through the
     * Event module's own facade rather than by referencing that project class directly. Missing this
     * listener is the single most likely cause of "I saved a setting in Zed and the storefront still
     * shows the old value" — the save persists correctly, it just never republishes to the KV store Yves
     * reads.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkEventListenerRegistered(OutputInterface $output): void
    {
        $listenersByEventName = $this->getFactory()->getEventFacade()->dumpEventListener();

        if (!empty($listenersByEventName[SearchRankingEvents::RANKING_CONFIGURATION_CHANGE])) {
            $output->writeln('<info>✓</info> a listener is registered for the ranking-configuration publish event');

            return;
        }

        $this->failures[] = sprintf(
            'No listener is registered for the "%s" event. Register SearchRankingStorageEventSubscriber in Pyz\Zed\Event\EventDependencyProvider::getEventSubscriberCollection() (README step 12) — without it, saving a metric or setting in Zed persists correctly but never reaches the synced key-value storage the live storefront reads.',
            SearchRankingEvents::RANKING_CONFIGURATION_CHANGE,
        );
    }

    /**
     * The sibling gap to {@see checkEventListenerRegistered()}: a project can register the publish event
     * listener (step 12) but forget `SearchRankingConfigurationSynchronizationDataPlugin` in
     * `Pyz\Zed\Synchronization\SynchronizationDependencyProvider::getSynchronizationDataPlugins()`
     * (step 11), or vice versa — either half missing produces the exact same symptom (a saved setting
     * never reaches Yves), so both need their own check. Verified via
     * {@see \Spryker\Zed\Synchronization\Business\SynchronizationFacadeInterface::getAvailableResourceNames()}.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkSyncQueueRegistered(OutputInterface $output): void
    {
        $availableResourceNames = $this->getFactory()->getSynchronizationFacade()->getAvailableResourceNames();

        if (in_array(SearchRankingStorageConfig::SEARCH_RANKING_CONFIGURATION_RESOURCE_NAME, $availableResourceNames, true)) {
            $output->writeln('<info>✓</info> the ranking-configuration sync queue is registered');

            return;
        }

        $this->failures[] = sprintf(
            'The "%s" synchronization resource is NOT registered. Add SearchRankingConfigurationSynchronizationDataPlugin in Pyz\Zed\Synchronization\SynchronizationDependencyProvider::getSynchronizationDataPlugins(), plus the matching queue/processor registrations (README step 11) — without it, a saved setting never reaches the synced key-value storage the live storefront reads.',
            SearchRankingStorageConfig::SEARCH_RANKING_CONFIGURATION_RESOURCE_NAME,
        );
    }

    /**
     * Confirms the project loaded this package's own Zed translation catalog (README step 6) via
     * {@see \Spryker\Zed\Translator\Business\TranslatorFacadeInterface::has()} against one of its own
     * known strings — catches both a missing `spryker-community/*` glob in
     * `Pyz\Zed\Translator\TranslatorConfig::getCoreTranslationFilePathPatterns()` and a stale translator
     * cache (`translator:generate-cache` never run since this package was installed).
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkZedTranslationRegistered(OutputInterface $output): void
    {
        if ($this->getFactory()->getTranslatorFacade()->has(static::KNOWN_ZED_TRANSLATION_KEY, static::KNOWN_ZED_TRANSLATION_LOCALE)) {
            $output->writeln('<info>✓</info> the Zed GUI translation catalog is loaded');

            return;
        }

        $this->failures[] = sprintf(
            'The Zed translation catalog does not resolve "%s". Add the spryker-community/* glob to Pyz\Zed\Translator\TranslatorConfig::getCoreTranslationFilePathPatterns() (README step 6), then run translator:clean-cache and translator:generate-cache.',
            static::KNOWN_ZED_TRANSLATION_KEY,
        );
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkSearchEngine(OutputInterface $output): void
    {
        try {
            $searchElasticsearchConfig = new SearchElasticsearchConfig();
            $elasticaClient = (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());
            $info = $elasticaClient->request('')->getData();
        } catch (Throwable $exception) {
            $this->failures[] = sprintf('Search engine is not reachable: %s', $exception->getMessage());

            return;
        }

        $version = $info['version'] ?? [];
        $output->writeln(sprintf(
            '<info>✓</info> search engine reachable: %s %s (Lucene %s)',
            (string)($version['distribution'] ?? 'elasticsearch'),
            (string)($version['number'] ?? '?'),
            (string)($version['lucene_version'] ?? '?'),
        ));

        $this->checkPageIndex($elasticaClient, $searchElasticsearchConfig, $output);
    }

    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkPageIndex(Client $elasticaClient, SearchElasticsearchConfig $searchElasticsearchConfig, OutputInterface $output): void
    {
        $indexPrefix = $searchElasticsearchConfig->getIndexPrefix();

        try {
            $aliases = $elasticaClient->request('_aliases')->getData();
        } catch (Throwable $exception) {
            $this->warnings[] = sprintf('Could not list indexes (%s) — skipping page index checks.', $exception->getMessage());

            return;
        }

        $pageIndexes = [];

        foreach (array_keys($aliases) as $indexName) {
            if (!str_starts_with((string)$indexName, $indexPrefix) || !str_ends_with((string)$indexName, SearchRankingConfig::PAGE_SOURCE_IDENTIFIER)) {
                continue;
            }

            $pageIndexes[] = (string)$indexName;
        }

        if ($pageIndexes === []) {
            $this->failures[] = sprintf(
                'No "%s*...%s" index found. The catalog has not been exported yet — run the publish/sync pipeline before expecting business-signal ranking.',
                $indexPrefix,
                SearchRankingConfig::PAGE_SOURCE_IDENTIFIER,
            );

            return;
        }

        $output->writeln(sprintf('<info>✓</info> page index found: %s', implode(', ', $pageIndexes)));

        $this->checkScoresFieldMapped($elasticaClient, $pageIndexes[0], $output);
    }

    /**
     * Confirms the `scores` object this package's export plugins (README steps 9-10) add to the page
     * document actually made it into the LIVE index mapping — the single most common silent-failure point:
     * a shop that installed this package but never re-exported the catalog has a page index with no
     * `scores` field at all, and every product's business signal quietly evaluates to the floor value.
     *
     * @param \Elastica\Client $elasticaClient
     * @param string $indexName
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkScoresFieldMapped(Client $elasticaClient, string $indexName, OutputInterface $output): void
    {
        try {
            $mapping = $elasticaClient->getIndex($indexName)->getMapping();
        } catch (Throwable $exception) {
            $this->warnings[] = sprintf('Could not read the index mapping (%s) — skipping the scores-field check.', $exception->getMessage());

            return;
        }

        if (isset($mapping['properties']['scores'])) {
            $output->writeln('<info>✓</info> "scores" field is present in the page index mapping');

            return;
        }

        $this->failures[] = sprintf(
            'Index "%s" has no "scores" field in its mapping. Either the schema directory registration (README step 10) is missing, or the catalog has never been re-exported since installing this package.',
            $indexName,
        );
    }

    /**
     * A WARNING, never a failure: a fresh install legitimately has zero metrics until an admin adds real
     * ones (README steps 4-5 are optional example data, not a hard requirement) — but shipping with none
     * at all silently means every product ranks by pure text relevance with no business signal
     * contribution, which is worth flagging explicitly rather than leaving to be discovered later.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkActiveMetrics(OutputInterface $output): void
    {
        $activeMetricCount = count($this->getFacade()->getActiveMetricCollection(
            SearchRankingConfig::DEFAULT_SCOPE_STORE_NAME,
            SearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME,
        )->getMetrics());

        if ($activeMetricCount > 0) {
            $output->writeln(sprintf('<info>✓</info> %d active metric(s) configured', $activeMetricCount));

            return;
        }

        $this->warnings[] = 'No active metrics are configured — every product currently ranks by pure text relevance, with zero business-signal contribution.';
    }
}
