<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Console;

use Elastica\Client;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Kernel\KernelConstants;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Diagnoses a search-ranking installation.
 *
 * This package's own README installation section has 14 steps, and — same as search-debug's equivalent
 * command — almost every one of them fails SILENTLY when missed: a forgotten DependencyProvider wire-up
 * or an un-run cron produces no error, just a ranking that quietly stays pure text-relevance. This checks
 * every prerequisite reachable from the CLI and names the exact remedy for whatever is wrong.
 *
 * Deliberately honest about its own limits, same posture as
 * {@see \SprykerCommunity\Zed\SearchDebug\Communication\Console\SearchDebugCheckInstallationConsole}: it
 * cannot confirm the Yves-side `function_score` query expander is registered (step 12) or that a live
 * storefront search actually reflects the configured weights — those need a real search request to
 * verify, not a CLI probe. Also distinct from `search-ranking:check-compatibility`: that command asks
 * "does this ENGINE support what the package needs", this one asks "is THIS INSTALLATION wired up
 * correctly" — run both for a full picture.
 *
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
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
    public const COMMAND_DESCRIPTION = 'Diagnoses a search-ranking installation: core namespace, sibling console command registration, search engine reachability, page index shape, and configured metrics.';

    /**
     * @var string
     */
    protected const CORE_NAMESPACE = 'SprykerCommunity';

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
        $output->writeln('  - the Yves function_score query expander is registered (step 12)');
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
