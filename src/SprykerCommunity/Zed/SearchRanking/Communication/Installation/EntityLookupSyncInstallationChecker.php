<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Installation;

use Generated\Shared\Transfer\SearchRankingEntityLookupSyncDiagnosisTransfer;
use ReflectionMethod;
use Spryker\Zed\Kernel\ClassResolver\Config\BundleConfigResolver;
use Spryker\Zed\Kernel\ClassResolver\DependencyProvider\DependencyProviderResolver;
use Spryker\Zed\ProductPageSearch\ProductPageSearchDependencyProvider;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingSuggestIndexEntityLookupRebuildConsole;
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingEntityLookupSyncPlugin;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;
use Throwable;

/**
 * Split out of {@see \SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole}
 * (which grew past this package's phpmd class-size threshold): everything
 * `checkEntityLookupSyncConfiguration()` needs to establish the raw facts, isolated the same way
 * {@see \SprykerCommunity\Zed\SearchRanking\Communication\Acl\BackOfficeAccessAnalyzer} already is for that
 * console's back-office-access check — a single-consumer Communication-layer helper, wired through
 * {@see \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory}, returning a
 * transfer of facts rather than deciding what they mean.
 */
class EntityLookupSyncInstallationChecker implements EntityLookupSyncInstallationCheckerInterface
{
    /**
     * Referenced as a string, never imported: spryker/symfony-scheduler is a `suggest`, not a
     * requirement, so this package must stay loadable without it.
     *
     * @var string
     */
    protected const SCHEDULER_CONFIG_CLASS = 'Spryker\\Zed\\SymfonyScheduler\\SymfonySchedulerConfig';

    /**
     * Referenced as a string for the same reason as {@see SCHEDULER_CONFIG_CLASS}: resolved via
     * {@see \Spryker\Zed\Kernel\ClassResolver\DependencyProvider\DependencyProviderResolver} to find
     * whichever project namespace's own override exists (falling back to core's own, which registers
     * nothing of this package's) — see {@see isEventHookRegistered()}.
     *
     * @var string
     */
    protected const PRODUCT_PAGE_SEARCH_DEPENDENCY_PROVIDER_CLASS = ProductPageSearchDependencyProvider::class;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $searchRankingConfig
     */
    public function __construct(protected SearchRankingConfig $searchRankingConfig)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function check(): SearchRankingEntityLookupSyncDiagnosisTransfer
    {
        $eventHookRegistered = $this->isEventHookRegistered();

        return (new SearchRankingEntityLookupSyncDiagnosisTransfer())
            ->setCronConfigured($this->isCronConfigured())
            ->setEventHookRegistered($eventHookRegistered ?? false)
            ->setEventHookRegistrationUnknown($eventHookRegistered === null);
    }

    /**
     * The "declared cron" signal. Prefers real introspection via {@see findRegisteredCronJobs()} — if
     * `search-ranking:entity-lookup:suggest-index:rebuild` shows up in the resolved scheduler config, cron
     * mode is genuinely wired, not just declared. Falls back to
     * {@see \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig::isEntityLookupCronConfigured()}'s
     * self-declared flag when the scheduler config isn't resolvable (module absent, or a project schedules
     * jobs another way entirely) — see that method's own docblock.
     */
    protected function isCronConfigured(): bool
    {
        if ($this->searchRankingConfig->isEntityLookupCronConfigured()) {
            return true;
        }

        $cronJobs = $this->findRegisteredCronJobs();

        if ($cronJobs === null) {
            return false;
        }

        $registeredCommands = implode(' ', array_column($cronJobs, 'command'));

        return str_contains($registeredCommands, SearchRankingSuggestIndexEntityLookupRebuildConsole::COMMAND_NAME);
    }

    /**
     * Null means "cannot tell" (module absent, or the resolved config does not expose cron jobs).
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
     * The "verified event-hook wiring" signal — real introspection of the resolved
     * `Pyz\Zed\ProductPageSearch\ProductPageSearchDependencyProvider::getDataLoaderPlugins()` plugin stack
     * (a protected method, reached via {@see \ReflectionMethod} the same way core's own bootstrap invokes
     * it — there is no public API to list a bundle's registered plugins), NOT the
     * `isEntityLookupEventSyncEnabled()` config flag: a registered-but-disabled plugin and an
     * enabled-but-never-registered flag both mean the event-hook does nothing, but only THIS check can
     * tell "class registered" apart from "class never wired at all", which is the failure this package can
     * actually help a project catch (a stale config flag alone cannot).
     *
     * Null means "cannot tell" (module absent, or the resolved provider couldn't be reflected into).
     */
    protected function isEventHookRegistered(): ?bool
    {
        if (!class_exists(static::PRODUCT_PAGE_SEARCH_DEPENDENCY_PROVIDER_CLASS)) {
            return null;
        }

        try {
            $dependencyProvider = (new DependencyProviderResolver())->resolve(static::PRODUCT_PAGE_SEARCH_DEPENDENCY_PROVIDER_CLASS);

            if (!method_exists($dependencyProvider, 'getDataLoaderPlugins')) {
                return null;
            }

            $reflectionMethod = new ReflectionMethod($dependencyProvider, 'getDataLoaderPlugins');
            $dataLoaderPlugins = $reflectionMethod->invoke($dependencyProvider);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($dataLoaderPlugins)) {
            return null;
        }

        foreach ($dataLoaderPlugins as $dataLoaderPlugin) {
            if ($dataLoaderPlugin instanceof SearchRankingEntityLookupSyncPlugin) {
                return true;
            }
        }

        return false;
    }
}
