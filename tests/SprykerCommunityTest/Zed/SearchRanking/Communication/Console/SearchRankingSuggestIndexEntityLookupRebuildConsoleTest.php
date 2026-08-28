<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Console;

use Codeception\Test\Unit;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingSuggestIndexEntityLookupRebuildConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Console
 * @group SearchRankingSuggestIndexEntityLookupRebuildConsoleTest
 * @group Portable
 */
class SearchRankingSuggestIndexEntityLookupRebuildConsoleTest extends Unit
{
    public function testReportsNothingRebuiltWhenTheFacadeReturnsAnEmptyResult(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester([]);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingSuggestIndexEntityLookupRebuildConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('Nothing rebuilt — check --type, --store, and --locale.', $commandTester->getDisplay());
    }

    public function testReportsTheWrittenCountPerStoreWhenTheFacadeReturnsResults(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(['DE' => 12, 'AT' => 3]);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $display = $commandTester->getDisplay();
        $this->assertSame(SearchRankingSuggestIndexEntityLookupRebuildConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('DE: 12 documents written.', $display);
        $this->assertStringContainsString('AT: 3 documents written.', $display);
    }

    /**
     * The `--type`/`--store`/`--locale` options must reach the Facade call unchanged, not silently dropped
     * — and `--type` must default to "sku" when omitted.
     */
    public function testOptionsAreForwardedToTheFacadeAndTypeDefaultsToSku(): void
    {
        // Arrange
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['rebuildSuggestIndexEntityLookup'])
            ->getMock();
        $facadeMock->expects($this->once())
            ->method('rebuildSuggestIndexEntityLookup')
            ->with(SearchRankingConfig::ENTITY_LOOKUP_TYPE_SKU, 'DE', 'de_DE')
            ->willReturn(['DE' => 1]);

        $console = new SearchRankingSuggestIndexEntityLookupRebuildConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);
        $commandTester = new CommandTester($application->find(SearchRankingSuggestIndexEntityLookupRebuildConsole::COMMAND_NAME));

        // Act
        $exitCode = $commandTester->execute(['--store' => 'DE', '--locale' => 'de_DE']);

        // Assert
        $this->assertSame(SearchRankingSuggestIndexEntityLookupRebuildConsole::CODE_SUCCESS, $exitCode);
    }

    /**
     * An explicit `--type` must reach the Facade call as given, not be overridden by the default.
     */
    public function testAnExplicitTypeOptionOverridesTheDefault(): void
    {
        // Arrange
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['rebuildSuggestIndexEntityLookup'])
            ->getMock();
        $facadeMock->expects($this->once())
            ->method('rebuildSuggestIndexEntityLookup')
            ->with('brand', null, null)
            ->willReturn([]);

        $console = new SearchRankingSuggestIndexEntityLookupRebuildConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);
        $commandTester = new CommandTester($application->find(SearchRankingSuggestIndexEntityLookupRebuildConsole::COMMAND_NAME));

        // Act
        $exitCode = $commandTester->execute(['--type' => 'brand']);

        // Assert
        $this->assertSame(SearchRankingSuggestIndexEntityLookupRebuildConsole::CODE_SUCCESS, $exitCode);
    }

    /**
     * @param array<string, int> $writtenCountByStoreName
     */
    protected function createCommandTester(array $writtenCountByStoreName): CommandTester
    {
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['rebuildSuggestIndexEntityLookup'])
            ->getMock();
        $facadeMock->method('rebuildSuggestIndexEntityLookup')->willReturn($writtenCountByStoreName);

        $console = new SearchRankingSuggestIndexEntityLookupRebuildConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchRankingSuggestIndexEntityLookupRebuildConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
