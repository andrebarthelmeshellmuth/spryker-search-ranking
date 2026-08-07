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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Only `checkActiveMetrics()` and `checkSiblingCommandsRegistered()` are exercised under fully controlled
 * conditions here (the Facade is mocked, and the test builds the sibling `Application` itself) — the
 * search-engine/page-index/scores-field checks, and now `checkEventListenerRegistered()`, deliberately hit
 * the REAL Elasticsearch and this demoshop's OWN real project wiring, same portability tradeoff
 * {@see \SprykerCommunityTest\Client\SearchRankingOptimizer\Search\CalibrationSearcherTest} already
 * accepts: this command exists specifically to diagnose a REAL installation, a throwaway/mocked
 * dumpEventListener() would prove nothing about whether the project's own
 * `Pyz\Zed\Event\EventDependencyProvider` actually registers the listener. This demoshop's own
 * installation is expected to be fully wired (core namespace registered, catalog exported, `scores` field
 * mapped, ranking-configuration event listener registered) — asserted on accordingly.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Console
 * @group SearchRankingCheckInstallationConsoleTest
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
        $reflectionProperty->setAccessible(true);
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
        $this->assertStringContainsString('a listener is registered for the ranking-configuration publish event', $commandTester->getDisplay());
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
}
