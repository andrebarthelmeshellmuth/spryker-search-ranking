<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Console;

use Codeception\Test\Unit;
use ReflectionMethod;
use ReflectionProperty;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole;
use Symfony\Component\Console\Output\NullOutput;

/**
 * PORTABLE unit coverage for {@see SearchRankingCheckInstallationConsole::checkEntityLookupSyncConfiguration()}'s
 * 0/1/2 decision logic, isolated from both real Propel/project state — unlike
 * {@see SearchRankingCheckInstallationConsoleTest}, which exercises this same console end-to-end against
 * THIS demoshop's own real wiring (`@group NeedsProject`). Reaches the two protected signal methods
 * ({@see SearchRankingCheckInstallationConsole::isEntityLookupCronConfigured()}/
 * {@see SearchRankingCheckInstallationConsole::isEntityLookupSyncPluginRegistered()}) via an anonymous
 * subclass that overrides them with controlled values, so this test exercises ONLY the combination logic,
 * never real class-resolution/introspection.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Console
 * @group SearchRankingCheckInstallationConsoleEntityLookupSyncTest
 * @group Portable
 */
class SearchRankingCheckInstallationConsoleEntityLookupSyncTest extends Unit
{
    public function testFailsWhenNeitherSyncMechanismIsConfigured(): void
    {
        // Arrange
        [$failures, $warnings] = $this->runCheck(cronConfigured: false, eventHookRegistered: false);

        // Assert
        $this->assertCount(1, $failures);
        $this->assertStringContainsString('NOT configured', $failures[0]);
        $this->assertSame([], $warnings);
    }

    public function testFailsWhenBothSyncMechanismsAreConfigured(): void
    {
        // Arrange
        [$failures, $warnings] = $this->runCheck(cronConfigured: true, eventHookRegistered: true);

        // Assert
        $this->assertCount(1, $failures);
        $this->assertStringContainsString('configured TWICE', $failures[0]);
        $this->assertSame([], $warnings);
    }

    public function testPassesWithANoticeWhenOnlyCronIsConfigured(): void
    {
        // Arrange
        [$failures, $warnings] = $this->runCheck(cronConfigured: true, eventHookRegistered: false);

        // Assert
        $this->assertSame([], $failures);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('event-hook sync is not active', $warnings[0]);
    }

    public function testPassesWithANoticeWhenOnlyTheEventHookIsConfigured(): void
    {
        // Arrange
        [$failures, $warnings] = $this->runCheck(cronConfigured: false, eventHookRegistered: true);

        // Assert
        $this->assertSame([], $failures);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('cron sync is not declared', $warnings[0]);
    }

    public function testTreatsAnUnresolvableEventHookSignalAsNotRegistered(): void
    {
        // Arrange — `isEntityLookupSyncPluginRegistered()` returning null (module absent/unresolvable)
        // must not silently pass as "configured"; combined with cron also off, this is the 0-of-2 failure.
        [$failures, $warnings] = $this->runCheck(cronConfigured: false, eventHookRegistered: null);

        // Assert
        $this->assertCount(1, $failures);
        $this->assertStringContainsString('NOT configured', $failures[0]);
        $this->assertCount(1, $warnings, 'Expected exactly the "could not introspect" warning.');
        $this->assertStringContainsString('Could not introspect', $warnings[0]);
    }

    /**
     * @param bool $cronConfigured
     * @param bool|null $eventHookRegistered
     *
     * @return array{0: array<string>, 1: array<string>} [failures, warnings]
     */
    protected function runCheck(bool $cronConfigured, ?bool $eventHookRegistered): array
    {
        $console = new class extends SearchRankingCheckInstallationConsole {
            public bool $cronConfiguredOverride = false;

            public ?bool $eventHookRegisteredOverride = false;

            protected function isEntityLookupCronConfigured(): bool
            {
                return $this->cronConfiguredOverride;
            }

            protected function isEntityLookupSyncPluginRegistered(): ?bool
            {
                return $this->eventHookRegisteredOverride;
            }
        };
        $console->cronConfiguredOverride = $cronConfigured;
        $console->eventHookRegisteredOverride = $eventHookRegistered;

        $reflectionMethod = new ReflectionMethod($console, 'checkEntityLookupSyncConfiguration');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($console, new NullOutput());

        $failuresProperty = new ReflectionProperty(SearchRankingCheckInstallationConsole::class, 'failures');
        $failuresProperty->setAccessible(true);

        $warningsProperty = new ReflectionProperty(SearchRankingCheckInstallationConsole::class, 'warnings');
        $warningsProperty->setAccessible(true);

        return [$failuresProperty->getValue($console), $warningsProperty->getValue($console)];
    }
}
