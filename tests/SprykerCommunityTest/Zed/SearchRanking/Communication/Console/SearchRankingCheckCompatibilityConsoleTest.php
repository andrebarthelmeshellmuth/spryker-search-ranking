<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Console;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingEngineCapabilityTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckCompatibilityConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Mocks only `SearchRankingFacade::checkEngineCompatibility()` (via `Console::setFacade()`, the same
 * hook core's own console tests use — see e.g.
 * `vendor/spryker/search/tests/SprykerTest/Zed/Search/Communication/Console/GenerateIndexMapConsoleTest.php`)
 * so this proves the console's own exit-code/output branching without needing a real search engine.
 * {@see \SprykerCommunityTest\Client\SearchRanking\Search\EngineCompatibilityCheckerTest} already covers
 * the real-engine probing this command's Facade call ultimately delegates to.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Console
 * @group SearchRankingCheckCompatibilityConsoleTest
 * @group NeedsProject
 */
class SearchRankingCheckCompatibilityConsoleTest extends Unit
{
    public function testExitsSuccessfullyWhenTheProductionCapabilityIsSupported(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester($this->createCompatibilityTransfer(isProductionCapabilitySupported: true));

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingCheckCompatibilityConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('Engine: opensearch 1.3.4', $commandTester->getDisplay());
        $this->assertStringContainsString('function_score + script_score (painless)', $commandTester->getDisplay());
    }

    public function testExitsWithAnErrorWhenTheProductionCapabilityIsUnsupported(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester($this->createCompatibilityTransfer(isProductionCapabilitySupported: false));

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingCheckCompatibilityConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('is NOT supported by this engine', $commandTester->getDisplay());
    }

    /**
     * An unsupported OPTIONAL (forward-looking-only) capability must never flip the exit code — only the
     * one capability this package's CURRENT version actually depends on does.
     */
    public function testAnUnsupportedOptionalCapabilityDoesNotAffectTheExitCode(): void
    {
        // Arrange
        $compatibilityTransfer = $this->createCompatibilityTransfer(isProductionCapabilitySupported: true);
        $compatibilityTransfer->addCapability(
            (new SearchRankingEngineCapabilityTransfer())
                ->setNameOrFail('rank_feature')
                ->setIsSupportedOrFail(false)
                ->setDetailOrFail('not recognized'),
        );

        $commandTester = $this->createCommandTester($compatibilityTransfer);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingCheckCompatibilityConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('Optional — forward-looking only', $commandTester->getDisplay());
        $this->assertStringContainsString('rank_feature', $commandTester->getDisplay());
    }

    /**
     * @param bool $isProductionCapabilitySupported
     */
    protected function createCompatibilityTransfer(bool $isProductionCapabilitySupported): SearchRankingEngineCompatibilityTransfer
    {
        return (new SearchRankingEngineCompatibilityTransfer())
            ->setDistributionOrFail('opensearch')
            ->setVersionOrFail('1.3.4')
            ->addCapability(
                (new SearchRankingEngineCapabilityTransfer())
                    ->setNameOrFail('function_score + script_score (painless)')
                    ->setIsSupportedOrFail($isProductionCapabilitySupported)
                    ->setDetailOrFail($isProductionCapabilitySupported ? 'accepted' : 'rejected by _validate/query'),
            );
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer $compatibilityTransfer
     */
    protected function createCommandTester(SearchRankingEngineCompatibilityTransfer $compatibilityTransfer): CommandTester
    {
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['checkEngineCompatibility'])
            ->getMock();
        $facadeMock->method('checkEngineCompatibility')->willReturn($compatibilityTransfer);

        $console = new SearchRankingCheckCompatibilityConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchRankingCheckCompatibilityConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
