<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Console;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingScopeCopySyncConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Console
 * @group SearchRankingScopeCopySyncConsoleTest
 * @group Portable
 */
class SearchRankingScopeCopySyncConsoleTest extends Unit
{
    public function testReportsNoActiveLocksWhenNothingWasSynced(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(0);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingScopeCopySyncConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('No active scope-copy locks', $commandTester->getDisplay());
    }

    public function testReportsTheSyncedCountWhenLocksWereSynced(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(3);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingScopeCopySyncConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('Synced 3 active scope-copy lock(s).', $commandTester->getDisplay());
    }

    protected function createCommandTester(int $syncedCount): CommandTester
    {
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['runScopeCopyDailySync'])
            ->getMock();
        $facadeMock->method('runScopeCopyDailySync')->willReturn($syncedCount);

        $console = new SearchRankingScopeCopySyncConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchRankingScopeCopySyncConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
