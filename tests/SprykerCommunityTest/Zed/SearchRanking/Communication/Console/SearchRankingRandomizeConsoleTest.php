<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Console;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingRandomizeConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Console
 * @group SearchRankingRandomizeConsoleTest
 */
class SearchRankingRandomizeConsoleTest extends Unit
{
    public function testReportsTheReshuffleWhenTheRandomMetricIsActive(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(true);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingRandomizeConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('Reshuffled the random tie-breaker metric', $commandTester->getDisplay());
    }

    /**
     * A missing/inactive random metric is a safe, deliberate no-op — never an error, since it's meant to
     * stay scheduled regardless of whether that metric happens to be configured for a given scope.
     */
    public function testExitsSuccessfullyAsANoOpWhenTheRandomMetricIsNotActive(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(false);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingRandomizeConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('not active (or does not exist) — nothing to do.', $commandTester->getDisplay());
    }

    /**
     * The `--store`/`--locale` options must reach the Facade call unchanged, not silently dropped.
     */
    public function testStoreAndLocaleOptionsAreForwardedToTheFacade(): void
    {
        // Arrange
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['randomizeRandomMetricIfActive'])
            ->getMock();
        $facadeMock->expects($this->once())
            ->method('randomizeRandomMetricIfActive')
            ->with('DE', 'de_DE')
            ->willReturn(true);

        $console = new SearchRankingRandomizeConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);
        $commandTester = new CommandTester($application->find(SearchRankingRandomizeConsole::COMMAND_NAME));

        // Act
        $exitCode = $commandTester->execute(['--store' => 'DE', '--locale' => 'de_DE']);

        // Assert
        $this->assertSame(SearchRankingRandomizeConsole::CODE_SUCCESS, $exitCode);
    }

    /**
     * @param bool $wasRandomized
     */
    protected function createCommandTester(bool $wasRandomized): CommandTester
    {
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['randomizeRandomMetricIfActive'])
            ->getMock();
        $facadeMock->method('randomizeRandomMetricIfActive')->willReturn($wasRandomized);

        $console = new SearchRankingRandomizeConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        return new CommandTester($application->find(SearchRankingRandomizeConsole::COMMAND_NAME));
    }
}
