<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Console;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use ReflectionProperty;
use Spryker\Zed\Event\Business\Subscriber\SubscriberMerger;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckCompatibilityConsole;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingNormalizeConsole;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingRandomizeConsole;
use SprykerCommunityTest\Zed\SearchRanking\Communication\Console\Fixture\GlueApiResourceFixture;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Only `checkActiveMetrics()` and `checkSiblingCommandsRegistered()` are exercised under fully controlled
 * conditions here (the Facade is mocked, and the test builds the sibling `Application` itself) — every
 * other check (search engine/page index/scores field, event listener, sync queue, Zed translation,
 * data-import plugins) deliberately hits the REAL Elasticsearch and this demoshop's OWN real project
 * wiring, same portability tradeoff {@see \SprykerCommunityTest\Client\SearchRankingOptimizer\Search\CalibrationSearcherTest}
 * already accepts: this command exists specifically to diagnose a REAL installation, throwaway/mocked
 * facades would prove nothing about whether the project's own DependencyProvider classes actually
 * register everything. This demoshop's own installation is expected to be fully wired (core namespace
 * registered, catalog exported, `scores` field mapped, ranking-configuration event listener + sync queue
 * registered, Zed translations loaded, data-import plugins registered) — asserted on accordingly.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Console
 * @group SearchRankingCheckInstallationConsoleTest
 * @group NeedsProject
 */
class SearchRankingCheckInstallationConsoleTest extends Unit
{
    /**
     * `Spryker\Zed\Event\Business\Subscriber\SubscriberMerger` caches its merged listener list in a
     * process-static property, computed once and frozen for the rest of the PHP process — harmless for
     * real CLI usage (one console invocation = one fresh process), but this test class runs all 3 tests
     * in one shared Codeception process, so a stale merge from whichever test happens to trigger it first
     * would silently poison every later test's `dumpEventListener()` call. Reset it before each test so
     * `checkEventListenerRegistered()` always recomputes against this demoshop's real, current wiring.
     */
    protected function _before(): void
    {
        $reflectionProperty = new ReflectionProperty(SubscriberMerger::class, 'eventCollectionBuffer');
        $reflectionProperty->setValue(null, null);
    }

    public function testSucceedsAndReportsEveryCheckWhenSiblingCommandsAreRegisteredAndMetricsAreConfigured(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester($this->createActiveMetricCollection(2), registerAllSiblingCommands: true);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingCheckInstallationConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('core namespace "SprykerCommunity" is registered', $commandTester->getDisplay());
        $this->assertStringContainsString('all 3 sibling console commands are registered', $commandTester->getDisplay());
        $this->assertStringContainsString('all 2 data-import plugins are registered', $commandTester->getDisplay());
        $this->assertStringContainsString('entity-lookup sync: event-hook mode wired', $commandTester->getDisplay());
        $this->assertStringContainsString('a listener is registered for the ranking-configuration publish event', $commandTester->getDisplay());
        $this->assertStringContainsString('the ranking-configuration sync queue is registered', $commandTester->getDisplay());
        $this->assertStringContainsString('the Zed GUI translation catalog is loaded', $commandTester->getDisplay());
        $this->assertStringContainsString('2 active metric(s) configured', $commandTester->getDisplay());
        $this->assertStringContainsString('Everything checkable from the CLI is in place.', $commandTester->getDisplay());
    }

    /**
     * A missing sibling command is a FAILURE (not a warning) — it means README step 3 was never
     * completed, which this command must not report as a clean bill of health.
     */
    public function testFailsAndNamesTheMissingCommandWhenASiblingCommandIsNotRegistered(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester($this->createActiveMetricCollection(1), registerAllSiblingCommands: false);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingCheckInstallationConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('search-ranking:randomize', $commandTester->getDisplay());
        $this->assertStringContainsString('NOT registered', $commandTester->getDisplay());
    }

    /**
     * Zero active metrics is a WARNING, never a failure — a fresh install legitimately has none yet.
     */
    public function testSucceedsWithAWarningWhenNoActiveMetricsAreConfigured(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester($this->createActiveMetricCollection(0), registerAllSiblingCommands: true);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingCheckInstallationConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('No active metrics are configured', $commandTester->getDisplay());
    }

    public function testWarnsAndNamesTheRemedyWhenTheSchemaMergeHasNotHappened(): void
    {
        // Arrange — a class name nothing ever defines, standing in for the merged schema never having been generated.
        $commandTester = $this->createCommandTesterWithGlueApiWiring(
            resourceClassName: 'Generated\\Api\\Storefront\\DoesNotExistResource' . uniqid(),
            overrideFilePath: sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.php',
        );

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert — optional (a project may not run Glue Storefront at all), so still CODE_SUCCESS.
        $this->assertSame(SearchRankingCheckInstallationConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('does not have a getRandomImpact() accessor yet', $commandTester->getDisplay());
        $this->assertStringNotContainsString('schema merge:', $commandTester->getDisplay());
        $this->assertStringNotContainsString('wires randomImpact into the Glue response', $commandTester->getDisplay());
    }

    public function testWarnsAndNamesTheRemedyWhenTheSchemaMergedButNoOverrideExists(): void
    {
        // Arrange
        $commandTester = $this->createCommandTesterWithGlueApiWiring(
            resourceClassName: GlueApiResourceFixture::class,
            overrideFilePath: sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.php',
        );

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingCheckInstallationConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('has a randomImpact property', $commandTester->getDisplay());
        $this->assertStringContainsString('no project-level', $commandTester->getDisplay());
        $this->assertStringContainsString('override exists', $commandTester->getDisplay());
        $this->assertStringNotContainsString('wires randomImpact into the Glue response', $commandTester->getDisplay());
    }

    public function testWarnsAndNamesTheRemedyWhenTheOverrideExistsButDoesNotReferenceRandomImpact(): void
    {
        // Arrange
        $overrideFilePath = $this->createOverrideFileFixture('<?php class CatalogSearchStorefrontProvider {}');

        try {
            $commandTester = $this->createCommandTesterWithGlueApiWiring(
                resourceClassName: GlueApiResourceFixture::class,
                overrideFilePath: $overrideFilePath,
            );

            // Act
            $exitCode = $commandTester->execute([]);

            // Assert
            $this->assertSame(SearchRankingCheckInstallationConsole::CODE_SUCCESS, $exitCode);
            $this->assertStringContainsString('exists but does not reference "randomImpact"', $commandTester->getDisplay());
            $this->assertStringNotContainsString('wires randomImpact into the Glue response', $commandTester->getDisplay());
        } finally {
            unlink($overrideFilePath);
        }
    }

    public function testSucceedsWithoutWarningWhenTheGlueApiWiringIsComplete(): void
    {
        // Arrange
        $overrideFilePath = $this->createOverrideFileFixture('<?php class CatalogSearchStorefrontProvider { public function provideCollection() { $resourceData["randomImpact"] = $searchResult[SearchRankingConfig::RANDOM_IMPACT_RESULT_KEY] ?? []; } }');

        try {
            $commandTester = $this->createCommandTesterWithGlueApiWiring(
                resourceClassName: GlueApiResourceFixture::class,
                overrideFilePath: $overrideFilePath,
            );

            // Act
            $exitCode = $commandTester->execute([]);

            // Assert
            $this->assertSame(SearchRankingCheckInstallationConsole::CODE_SUCCESS, $exitCode);
            $this->assertStringContainsString('has a randomImpact property', $commandTester->getDisplay());
            $this->assertStringContainsString('wires randomImpact into the Glue response', $commandTester->getDisplay());
            $this->assertStringNotContainsString('does not have a getRandomImpact', $commandTester->getDisplay());
            $this->assertStringNotContainsString('no project-level', $commandTester->getDisplay());
        } finally {
            unlink($overrideFilePath);
        }
    }

    /**
     * @param int $count
     */
    protected function createActiveMetricCollection(int $count): SearchRankingMetricCollectionTransfer
    {
        $metrics = new ArrayObject();

        for ($i = 0; $i < $count; $i++) {
            $metrics->append((new SearchRankingMetricTransfer())->setNameOrFail('metric_' . $i));
        }

        return (new SearchRankingMetricCollectionTransfer())->setMetrics($metrics);
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer $activeMetricCollection
     * @param bool $registerAllSiblingCommands
     */
    protected function createCommandTester(SearchRankingMetricCollectionTransfer $activeMetricCollection, bool $registerAllSiblingCommands): CommandTester
    {
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['getActiveMetricCollection'])
            ->getMock();
        $facadeMock->method('getActiveMetricCollection')->willReturn($activeMetricCollection);

        $console = new SearchRankingCheckInstallationConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);
        $application->add(new SearchRankingNormalizeConsole());

        if ($registerAllSiblingCommands) {
            $application->add(new SearchRankingRandomizeConsole());
        }

        $application->add(new SearchRankingCheckCompatibilityConsole());

        $command = $application->find(SearchRankingCheckInstallationConsole::COMMAND_NAME);

        return new CommandTester($command);
    }

    /**
     * All sibling commands registered and one active metric, same happy-path shape as
     * {@see createCommandTester()}, but with an anonymous subclass overriding
     * {@see SearchRankingCheckInstallationConsole::getGlueApiResourceClassName()} and
     * {@see SearchRankingCheckInstallationConsole::getGlueApiProviderOverrideFilePath()} so the Glue API
     * wiring check tests fixtures instead of this host shop's real generated resource / real project
     * override file.
     */
    protected function createCommandTesterWithGlueApiWiring(string $resourceClassName, string $overrideFilePath): CommandTester
    {
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['getActiveMetricCollection'])
            ->getMock();
        $facadeMock->method('getActiveMetricCollection')->willReturn($this->createActiveMetricCollection(1));

        $console = new class ($resourceClassName, $overrideFilePath) extends SearchRankingCheckInstallationConsole {
            public function __construct(protected string $resourceClassName, protected string $overrideFilePath)
            {
                parent::__construct();
            }

            protected function getGlueApiResourceClassName(): string
            {
                return $this->resourceClassName;
            }

            protected function getGlueApiProviderOverrideFilePath(): string
            {
                return $this->overrideFilePath;
            }
        };
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);
        $application->add(new SearchRankingNormalizeConsole());
        $application->add(new SearchRankingRandomizeConsole());
        $application->add(new SearchRankingCheckCompatibilityConsole());

        $command = $application->find(SearchRankingCheckInstallationConsole::COMMAND_NAME);

        return new CommandTester($command);
    }

    /**
     * Writes a throwaway PHP file standing in for a project's
     * `src/Pyz/Glue/CatalogSearchRestApi/Api/Storefront/Provider/CatalogSearchStorefrontProvider.php`
     * override — the console only ever reads this file's contents with `file_get_contents()`, it never
     * includes/parses it, so the contents don't need to be autoload-safe PHP, just contain (or not
     * contain) the literal string `randomImpact`.
     */
    protected function createOverrideFileFixture(string $contents): string
    {
        $overrideFilePath = tempnam(sys_get_temp_dir(), 'catalog-search-storefront-provider-override-fixture-');

        file_put_contents($overrideFilePath, $contents);

        return $overrideFilePath;
    }
}
