<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Console;

use ArrayObject;
use Elastica\Client;
use FilesystemIterator;
use Generated\Shared\Transfer\DataImportConfigurationActionTransfer;
use Generated\Shared\Transfer\DataImportConfigurationTransfer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Kernel\KernelConstants;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use Spryker\Zed\Kernel\ClassResolver\Config\BundleConfigResolver;
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
     * The commands this package expects a project to have put on a cron schedule.
     *
     * @var array<string>
     */
    protected const CRON_COMMANDS = [
        'search-ranking:normalize',
        'search-ranking:randomize',
        'search-ranking:scope-copy-sync',
    ];

    /**
     * Referenced as a string, never imported: spryker/symfony-scheduler is a `suggest`, not a
     * requirement, so this package must stay loadable without it.
     *
     * @var string
     */
    protected const SCHEDULER_CONFIG_CLASS = 'Spryker\\Zed\\SymfonyScheduler\\SymfonySchedulerConfig';

    /**
     * This package's own navigation.xml, relative to this console's directory — the source of truth for
     * which page keys a project is expected to have copied.
     *
     * @var string
     */
    protected const OWN_NAVIGATION_XML_RELATIVE_PATH = '/../../../SearchRankingGui/Communication/navigation.xml';

    /**
     * This package's root, relative to this console's directory.
     *
     * @var string
     */
    protected const PACKAGE_ROOT_RELATIVE_PATH = '/../../../../../..';

    /**
     * This package only ADDITIVELY merges a `randomImpact` property onto core's `catalog-search` resource
     * (spryker/catalog-search-rest-api) — the merged schema is what this class name check confirms exists.
     *
     * @var string
     */
    protected const GLUE_API_RESOURCE_CLASS_NAME = 'Generated\\Api\\Storefront\\CatalogSearchStorefrontResource';

    /**
     * README, "Glue REST API": the merge alone is not enough — a project-level Provider override is
     * required to actually copy the value into the response (the merged schema only describes SHAPE).
     * Relative to `APPLICATION_ROOT_DIR`, shared with spryker-community/search-debug's own `searchDebug`
     * property (both packages document registering this same override once).
     *
     * @var string
     */
    protected const GLUE_API_PROVIDER_OVERRIDE_RELATIVE_PATH = '/src/Pyz/Glue/CatalogSearchRestApi/Api/Storefront/Provider/CatalogSearchStorefrontProvider.php';

    /**
     * The locale whose catalog defines the expected key set; the others are kept at parity with it.
     *
     * @var string
     */
    protected const TRANSLATION_REFERENCE_LOCALE = 'en_US';

    /**
     * @var string
     */
    protected const PATTERN_TWIG_TRANS = '/(?<![\\w\\\\])([\'"])((?:\\\\.|(?!\\1).)*)\\1\\s*\\|\\s*trans/';

    /**
     * @var string
     */
    protected const PATTERN_PHP_TRANS = '/->(?:trans|translate)\\(\\s*([\'"])((?:\\\\.|(?!\\1).)*)\\1/';

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
        $this->checkCronJobsRegistered($output);
        $this->checkNavigationRegistered($output);
        $this->checkBackOfficeAccess($output);
        $this->checkZedTranslationCatalogComplete($output);
        $this->checkGlueApiWiring($output);

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

    /**
     * Cron jobs are the one integration step no package can perform for a project and nothing else
     * verifies: `SymfonySchedulerConfig::getCronJobs()` returns `[]` in Spryker core and has no plugin
     * stack at all, so a vendor package cannot contribute an entry — it is project config, copied by hand
     * from the README. Skipping it produces no error either, just a ranking that quietly keeps serving stale normalized scores.
     *
     * Resolved through {@see BundleConfigResolver} rather than by naming `Pyz\Zed\...` directly: the
     * resolver is what picks a project's own override over core's empty default, and hardcoding the
     * project namespace would break the moment a project uses a different one.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkCronJobsRegistered(OutputInterface $output): void
    {
        $cronJobs = $this->findRegisteredCronJobs();

        if ($cronJobs === null) {
            $this->warnings[] = sprintf(
                'Could not read this project\'s cron registrations (spryker/symfony-scheduler is optional and may not be installed, or this project schedules jobs another way). Confirm by hand that these run periodically: %s.',
                implode(', ', static::CRON_COMMANDS),
            );

            return;
        }

        $registeredCommands = implode(' ', array_column($cronJobs, 'command'));
        $missingCommands = [];

        foreach (static::CRON_COMMANDS as $commandName) {
            if (str_contains($registeredCommands, $commandName)) {
                continue;
            }

            $missingCommands[] = $commandName;
        }

        if ($missingCommands === []) {
            $output->writeln('<info>✓</info> every cron job this package needs is registered');

            return;
        }

        $this->failures[] = sprintf(
            'These commands are NOT scheduled: %s. Add them to Pyz\Zed\SymfonyScheduler\SymfonySchedulerConfig::getCronJobs() (README steps 8 and 15) — nothing registers them automatically, and leaving them unscheduled fails silently.',
            implode(', ', $missingCommands),
        );
    }

    /**
     * Null means "cannot tell" (module absent, or the resolved config does not expose cron jobs), which
     * is deliberately different from an empty array — the former is a warning, the latter a real failure.
     *
     * @return array<string, array<string, string>>|null
     */
    protected function findRegisteredCronJobs(): ?array
    {
        if (!class_exists(static::SCHEDULER_CONFIG_CLASS)) {
            return null;
        }

        try {
            $schedulerConfig = (new BundleConfigResolver())->resolve(static::SCHEDULER_CONFIG_CLASS);
        } catch (Throwable) {
            return null;
        }

        if (!method_exists($schedulerConfig, 'getCronJobs')) {
            return null;
        }

        return $schedulerConfig->getCronJobs();
    }

    /**
     * Zed navigation has no glob auto-discovery for `vendor/spryker-community/*`, so a project copies this
     * package's own `<search-ranking-gui>` block into `config/Zed/navigation.xml` by hand — and a page added by a
     * later version of this package is easy to miss on upgrade. Neither omission errors: the entry is
     * simply absent from the sidebar, and a stale navigation cache hides a correct copy just as
     * completely as never copying it at all.
     *
     * The expected page keys are read from this package's OWN navigation.xml rather than hardcoded here,
     * so this check cannot drift from what the package actually ships.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkNavigationRegistered(OutputInterface $output): void
    {
        $expectedPageKeys = $this->readOwnNavigationPageKeys();
        $effectiveNavigation = $this->readEffectiveNavigation();

        if ($expectedPageKeys === [] || $effectiveNavigation === null) {
            $this->warnings[] = 'Could not compare this package\'s navigation entries against the project\'s own (neither the built navigation cache nor config/Zed/navigation.xml was readable). Confirm by hand that this package\'s pages appear in the Zed sidebar.';

            return;
        }

        [$sourceLabel, $registeredPageKeys] = $effectiveNavigation;
        $missingPageKeys = array_values(array_diff($expectedPageKeys, $registeredPageKeys));

        if ($missingPageKeys === []) {
            $output->writeln(sprintf('<info>✓</info> all %d navigation entries are registered (checked against %s)', count($expectedPageKeys), $sourceLabel));

            return;
        }

        $this->failures[] = sprintf(
            'These navigation entries are missing from %s: %s. First run "vendor/bin/console navigation:cache:remove && vendor/bin/console navigation:build-cache" — a stale cache hides a correct configuration just as completely, and is the cheaper cause to rule out. If they are still missing after that, copy the <search-ranking-gui> block from this package\'s own src/SprykerCommunity/Zed/SearchRankingGui/Communication/navigation.xml into config/Zed/navigation.xml (README step 7). A missing entry never errors — the page simply cannot be reached from the sidebar.',
            $sourceLabel,
            implode(', ', $missingPageKeys),
        );
    }

    /**
     * Zed access is deny-by-default outside a matching ACL rule, and this package ships no ACL fixture data
     * — so who can reach its pages is entirely up to the adopter. Two very different installations land
     * here:
     *
     * A default Spryker install needs nothing done: `root_role` carries a total wildcard and every
     * installer user sits in `root_group`, so the pages work the moment the package is installed. An
     * installation running real restricted back-office roles is the opposite — those roles reach nothing
     * here until somebody adds a rule, and the failure is quiet, because
     * {@see \Spryker\Zed\Acl\Communication\Plugin\Navigation\AclNavigationItemFilterPlugin} filters the
     * entry out of the sidebar rather than 403ing. To that user the feature is simply absent, which looks
     * identical to the package never having been installed.
     *
     * A WARNING at most, and worded as something to confirm rather than fix: keeping these pages to
     * root-style admins is a perfectly ordinary choice, and this command cannot know which roles an adopter
     * MEANT to grant. It only reports the one state worth a second look — restricted roles exist, and not
     * one of them has a rule for this package's modules.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkBackOfficeAccess(OutputInterface $output): void
    {
        $moduleNames = $this->readOwnNavigationModuleNames();

        if ($moduleNames === []) {
            $this->warnings[] = 'Could not read this package\'s own navigation.xml, so back-office access could not be checked. Confirm by hand that the Zed roles which should see the Search Ranking pages can actually reach them.';

            return;
        }

        $diagnosisTransfer = $this->getFactory()->createBackOfficeAccessAnalyzer()->analyze($moduleNames);
        $restrictedRoleCount = $diagnosisTransfer->getRestrictedRoleCountOrFail();

        if ($restrictedRoleCount === 0) {
            $output->writeln(sprintf(
                '<info>✓</info> all %d back-office role(s) have unrestricted access, so this package\'s Zed pages need no ACL rule',
                $diagnosisTransfer->getUnrestrictedRoleCountOrFail(),
            ));

            return;
        }

        $restrictedRoleWithAccessCount = $diagnosisTransfer->getRestrictedRoleWithAccessCountOrFail();

        if ($restrictedRoleWithAccessCount > 0) {
            $output->writeln(sprintf(
                '<info>✓</info> %d of %d restricted back-office role(s) have an ACL rule for %s',
                $restrictedRoleWithAccessCount,
                $restrictedRoleCount,
                implode('/', $moduleNames),
            ));

            return;
        }

        $this->warnings[] = sprintf(
            'This project has %d restricted back-office role(s) and none of them has an ACL rule for %s, so only unrestricted (root-style) admins can reach this package\'s Zed pages — for everybody else the sidebar entry is filtered out entirely, which looks the same as the package not being installed. If that is intended, nothing to do. If a restricted role should see Search Ranking, add a rule for it in the Zed ACL Gui (Maintenance > Users & Rights > Roles).',
            $restrictedRoleCount,
            implode('/', $moduleNames),
        );
    }

    /**
     * Read from this package's OWN navigation.xml rather than hardcoded, same as the page-key check
     * alongside it, so a module added by a later version cannot silently fall out of this check.
     *
     * @return array<string>
     */
    protected function readOwnNavigationModuleNames(): array
    {
        $ownNavigationXml = $this->loadXml(__DIR__ . static::OWN_NAVIGATION_XML_RELATIVE_PATH);

        if ($ownNavigationXml === null) {
            return [];
        }

        $moduleNames = [];

        foreach ($ownNavigationXml->xpath('//bundle') ?: [] as $bundleElement) {
            $moduleNames[(string)$bundleElement] = true;
        }

        return array_keys($moduleNames);
    }

    /**
     * Every page key this package's own navigation.xml declares — the root entry plus each `<pages>`
     * child, including the ones marked `<visible>0</visible>` (invisible still means routable, and a
     * project that skipped them gets a dead link from the visible pages that point at them).
     *
     * @return array<string>
     */
    protected function readOwnNavigationPageKeys(): array
    {
        $ownNavigationXml = $this->loadXml(__DIR__ . static::OWN_NAVIGATION_XML_RELATIVE_PATH);

        if ($ownNavigationXml === null) {
            return [];
        }

        $pageKeys = [];

        foreach ($ownNavigationXml->children() as $rootEntry) {
            $pageKeys[] = $rootEntry->getName();

            foreach ($rootEntry->pages->children() as $page) {
                $pageKeys[] = $page->getName();
            }
        }

        return $pageKeys;
    }

    /**
     * Prefers the BUILT navigation cache over the project's raw XML, because the cache is what Zed
     * actually renders from — a correct copy that was never followed by a cache rebuild is a real, and
     * easy to miss, failure mode. Falls back to the raw XML when no cache has been built.
     *
     * @return array{0: string, 1: array<string>}|null
     */
    protected function readEffectiveNavigation(): ?array
    {
        $cacheFilePath = APPLICATION_ROOT_DIR . '/src/Generated/Zed/Navigation/codeBucket/navigation.cache';

        if (is_readable($cacheFilePath)) {
            $cachedNavigation = json_decode((string)file_get_contents($cacheFilePath), true);

            if (is_array($cachedNavigation)) {
                return ['the built navigation cache', $this->collectCachedPageKeys($cachedNavigation)];
            }
        }

        $projectPageKeys = $this->readProjectNavigationPageKeys();

        return $projectPageKeys === null ? null : ['config/Zed/navigation.xml', $projectPageKeys];
    }

    /**
     * @return array<string>|null
     */
    protected function readProjectNavigationPageKeys(): ?array
    {
        $projectNavigationXml = $this->loadXml(APPLICATION_ROOT_DIR . '/config/Zed/navigation.xml');

        if ($projectNavigationXml === null) {
            return null;
        }

        $pageKeys = [];

        foreach ($projectNavigationXml->xpath('//*') ?: [] as $element) {
            $pageKeys[] = $element->getName();
        }

        return $pageKeys;
    }

    /**
     * @param array<string, mixed> $cachedNavigation
     *
     * @return array<string>
     */
    protected function collectCachedPageKeys(array $cachedNavigation): array
    {
        $pageKeys = [];

        foreach ($cachedNavigation as $pageKey => $page) {
            $pageKeys[] = (string)$pageKey;

            if (!is_array($page) || !is_array($page['pages'] ?? null)) {
                continue;
            }

            $pageKeys = array_merge($pageKeys, $this->collectCachedPageKeys($page['pages']));
        }

        return $pageKeys;
    }

    /**
     * @param string $filePath
     */
    protected function loadXml(string $filePath): ?SimpleXMLElement
    {
        if (!is_readable($filePath)) {
            return null;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string)file_get_contents($filePath));
        libxml_use_internal_errors($previousUseInternalErrors);

        return $xml === false ? null : $xml;
    }

    /**
     * The Zed catalog and the strings the GUI actually renders drift apart silently, in both directions,
     * because the keys ARE the English text: a key missing from the catalog still renders correct English
     * and only shows up as untranslated in a non-English Zed. Nothing else notices, which is how this
     * package's own catalog fell behind its GUI once already.
     *
     * Scans this package's own Zed sources for `|trans` keys and asserts each one is in the shipped
     * catalog. Deliberately one-directional: a key that looks unused to this scan may still be reached
     * through addSuccessMessage(), a widget_title, a table header or a form label, all of which are
     * translated at render time, so an unused-looking entry is never reported as a problem.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkZedTranslationCatalogComplete(OutputInterface $output): void
    {
        $usedKeys = $this->collectUsedZedTranslationKeys();
        $catalogKeys = $this->readZedTranslationCatalogKeys(static::TRANSLATION_REFERENCE_LOCALE);

        if ($usedKeys === [] || $catalogKeys === null) {
            $this->warnings[] = 'Could not compare this package\'s Zed translation catalog against the strings its GUI uses (sources or catalog unreadable). Nothing to act on unless you are working on the package itself.';

            return;
        }

        $missingKeys = array_values(array_diff($usedKeys, $catalogKeys));

        if ($missingKeys === []) {
            $output->writeln(sprintf('<info>✓</info> all %d Zed GUI strings are present in the translation catalog', count($usedKeys)));

            return;
        }

        $this->failures[] = sprintf(
            '%d Zed GUI string(s) are missing from data/translation/Zed/%s.csv and will render untranslated in any non-English Zed: "%s". This is a defect in the package itself, not in your project setup.',
            count($missingKeys),
            static::TRANSLATION_REFERENCE_LOCALE,
            implode('", "', array_slice($missingKeys, 0, 8)) . (count($missingKeys) > 8 ? '", ...' : ''),
        );
    }

    /**
     * Two independent things have to both be true for `randomImpact` to actually appear on
     * `GET /catalog-search` (README, "Glue REST API"), and only the FIRST is something core/this
     * package's own composer install guarantees:
     *
     * 1. The additive schema merge ran: `Generated\Api\Storefront\CatalogSearchStorefrontResource` (core's
     *    resource, merged with this package's own `resources/api/storefront/catalog-search.resource.yml`)
     *    has a `getRandomImpact()` accessor. A project on a composer PATH REPOSITORY install (this
     *    demoshop's own setup) can silently fail this even with everything else correct — Symfony's
     *    `Finder` does not descend into symlinked directories without `->followLinks()`, so this package's
     *    schema file is invisible to `glue api:generate` unless the project registered
     *    `Pyz\Glue\ApiPlatformSymlinkFix\{SchemaFinder,ValidationSchemaFinder}` (see search-debug's README,
     *    "Glue REST API", for the full analysis — shared across every community package).
     * 2. A project-level Provider override actually copies the value in at request time — the merged
     *    schema only describes SHAPE, nothing in either package populates the response without it. Not
     *    optional in the sense frozen replay is optional elsewhere in this command family: a project that
     *    has done step 1 almost certainly intends `randomImpact` to actually work, so a missing override
     *    here is worth a WARNING either way — but neither half can be a FAILURE, since a project that does
     *    not run a Glue Storefront application at all is a legitimate, common configuration.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkGlueApiWiring(OutputInterface $output): void
    {
        $resourceClassName = $this->getGlueApiResourceClassName();

        if (!class_exists($resourceClassName) || !method_exists($resourceClassName, 'getRandomImpact')) {
            $this->warnings[] = sprintf(
                '%s does not have a getRandomImpact() accessor yet: either `vendor/bin/glue api:generate storefront` has not been run since this package was installed, or (on a composer path-repository install) the Finder symlink-traversal fix from search-debug\'s README, "Glue REST API" is missing. GET /catalog-search will not include randomImpact until this is resolved. Skip this if your project does not run a Glue Storefront application.',
                $resourceClassName,
            );

            return;
        }

        $output->writeln(sprintf('<info>✓</info> Glue API schema merge: %s has a randomImpact property', $resourceClassName));

        $overrideFilePath = $this->getGlueApiProviderOverrideFilePath();

        if (!is_readable($overrideFilePath)) {
            $this->warnings[] = sprintf(
                'The schema merge is in place, but no project-level %s override exists (README, "Glue REST API"). The merged schema only describes SHAPE — without this override, randomImpact is silently omitted from every GET /catalog-search response.',
                static::GLUE_API_PROVIDER_OVERRIDE_RELATIVE_PATH,
            );

            return;
        }

        $overrideFileContents = (string)file_get_contents($overrideFilePath);

        if (!str_contains($overrideFileContents, SearchRankingConfig::RANDOM_IMPACT_RESULT_KEY)) {
            $this->warnings[] = sprintf(
                '%s exists but does not reference "%s" — randomImpact is still silently omitted from GET /catalog-search (README, "Glue REST API").',
                static::GLUE_API_PROVIDER_OVERRIDE_RELATIVE_PATH,
                SearchRankingConfig::RANDOM_IMPACT_RESULT_KEY,
            );

            return;
        }

        $output->writeln('<info>✓</info> project-level CatalogSearchStorefrontProvider override wires randomImpact into the Glue response');
    }

    /**
     * Isolated as its own method so a test can override it to point at a fixture class name instead of
     * this host shop's real generated Glue resource.
     */
    protected function getGlueApiResourceClassName(): string
    {
        return static::GLUE_API_RESOURCE_CLASS_NAME;
    }

    /**
     * Isolated as its own method so a test can override it to point at a fixture file instead of this
     * host shop's real `src/Pyz/Glue/CatalogSearchRestApi/Api/Storefront/Provider/CatalogSearchStorefrontProvider.php`.
     */
    protected function getGlueApiProviderOverrideFilePath(): string
    {
        return APPLICATION_ROOT_DIR . static::GLUE_API_PROVIDER_OVERRIDE_RELATIVE_PATH;
    }

    /**
     * @return array<string>
     */
    protected function collectUsedZedTranslationKeys(): array
    {
        $zedSourcePath = __DIR__ . static::PACKAGE_ROOT_RELATIVE_PATH . '/src/SprykerCommunity/Zed';

        if (!is_dir($zedSourcePath)) {
            return [];
        }

        $keys = [];
        $directoryIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($zedSourcePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($directoryIterator as $fileInfo) {
            if (!$fileInfo->isFile() || !in_array(strtolower($fileInfo->getExtension()), ['twig', 'php'], true)) {
                continue;
            }

            $keys = array_merge($keys, $this->extractTranslationKeys((string)file_get_contents($fileInfo->getPathname())));
        }

        return array_values(array_unique($keys));
    }

    /**
     * Skips anything interpolated (`~`, `{{ }}`) — those are built at runtime and cannot be matched
     * against a static catalog.
     *
     * @param string $source
     *
     * @return array<string>
     */
    protected function extractTranslationKeys(string $source): array
    {
        $keys = [];

        foreach ([static::PATTERN_TWIG_TRANS, static::PATTERN_PHP_TRANS] as $pattern) {
            preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $key = str_replace(['\\\'', '\\"'], ['\'', '"'], $match[2]);

                if (str_contains($key, '{') || str_contains($key, '~') || str_starts_with($key, '/')) {
                    continue;
                }

                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param string $locale
     *
     * @return array<string>|null
     */
    protected function readZedTranslationCatalogKeys(string $locale): ?array
    {
        $catalogPath = sprintf('%s%s/data/translation/Zed/%s.csv', __DIR__, static::PACKAGE_ROOT_RELATIVE_PATH, $locale);

        if (!is_readable($catalogPath)) {
            return null;
        }

        $handle = fopen($catalogPath, 'r');

        if ($handle === false) {
            return null;
        }

        $keys = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (!isset($row[0]) || trim((string)$row[0]) === '') {
                continue;
            }

            $keys[] = (string)$row[0];
        }

        fclose($handle);

        return $keys;
    }
}
