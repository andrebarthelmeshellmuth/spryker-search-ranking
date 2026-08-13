<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Console;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingNormalizeConsole;
use SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSearchRankingStorageFacadeInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Mocks `SearchRankingFacade` (via `Console::setFacade()`) and `SearchRankingCommunicationFactory` (via
 * `Console::setFactory()`) so this proves the console's own orchestration — option pass-through, the
 * `--skip-publish` gate, and the errors-drive-the-exit-code rule — without touching real Propel/
 * Elasticsearch. Each Business-layer call this console makes is already covered by its own dedicated
 * unit test (`ProductMetricNormalizerTest`, `MetricDigestBuilderTest`,
 * `ProductAbstractScorePublisherTest`, `RankingConfigurationStorageWriterTest`).
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Console
 * @group SearchRankingNormalizeConsoleTest
 * @group Portable
 */
class SearchRankingNormalizeConsoleTest extends Unit
{
    public function testExitsSuccessfullyAndPublishesWhenNormalizationHasNoErrors(): void
    {
        // Arrange
        $storageFacadeMock = $this->createMock(SearchRankingToSearchRankingStorageFacadeInterface::class);
        $storageFacadeMock->expects($this->once())->method('publishRankingConfiguration');

        $commandTester = $this->createCommandTester(
            $this->createResultTransfer(updatedRowCount: 42, processedMetricCount: 3, errors: []),
            publishedProductCount: 7,
            digestCount: 3,
            storageFacadeMock: $storageFacadeMock,
        );

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingNormalizeConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('Normalized 42 row(s) across 3 metric(s).', $commandTester->getDisplay());
        $this->assertStringContainsString('Rebuilt distribution digest for 3 metric(s).', $commandTester->getDisplay());
        $this->assertStringContainsString('Triggered publish events for 7 product abstract(s).', $commandTester->getDisplay());
        $this->assertStringContainsString('Published ranking configuration to key-value storage.', $commandTester->getDisplay());
    }

    public function testExitsWithAnErrorWhenAMetricFailsToNormalizeButStillCompletesTheRun(): void
    {
        // Arrange
        $storageFacadeMock = $this->createMock(SearchRankingToSearchRankingStorageFacadeInterface::class);
        $storageFacadeMock->expects($this->once())->method('publishRankingConfiguration');

        $commandTester = $this->createCommandTester(
            $this->createResultTransfer(updatedRowCount: 10, processedMetricCount: 2, errors: ['metric "broken" failed: division by zero']),
            publishedProductCount: 5,
            digestCount: 2,
            storageFacadeMock: $storageFacadeMock,
        );

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchRankingNormalizeConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('metric "broken" failed: division by zero', $commandTester->getDisplay());
        // A partial failure still completes the rest of the run rather than aborting early.
        $this->assertStringContainsString('Triggered publish events for 5 product abstract(s).', $commandTester->getDisplay());
    }

    /**
     * `--skip-publish` must skip BOTH the product-abstract publish AND the ranking-configuration
     * key-value publish — neither call may fire.
     */
    public function testSkipPublishOptionSkipsBothPublishStepsEntirely(): void
    {
        // Arrange
        $storageFacadeMock = $this->createMock(SearchRankingToSearchRankingStorageFacadeInterface::class);
        $storageFacadeMock->expects($this->never())->method('publishRankingConfiguration');

        $commandTester = $this->createCommandTester(
            $this->createResultTransfer(updatedRowCount: 10, processedMetricCount: 2, errors: []),
            publishedProductCount: 0,
            digestCount: 2,
            storageFacadeMock: $storageFacadeMock,
        );

        // Act
        $exitCode = $commandTester->execute(['--skip-publish' => true]);

        // Assert
        $this->assertSame(SearchRankingNormalizeConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringNotContainsString('Triggered publish events', $commandTester->getDisplay());
        $this->assertStringNotContainsString('Published ranking configuration', $commandTester->getDisplay());
    }

    /**
     * The `--store`/`--locale` options must reach the Facade call unchanged, not silently dropped.
     */
    public function testStoreAndLocaleOptionsAreForwardedToTheFacade(): void
    {
        // Arrange
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['normalizeProductMetricValues', 'rebuildMetricDigests', 'publishScoredProductAbstracts'])
            ->getMock();
        $facadeMock->expects($this->once())
            ->method('normalizeProductMetricValues')
            ->with('DE', 'de_DE')
            ->willReturn($this->createResultTransfer(0, 0, []));
        $facadeMock->method('rebuildMetricDigests')->with('DE', 'de_DE')->willReturn(0);
        $facadeMock->method('publishScoredProductAbstracts')->willReturn(0);

        $console = new SearchRankingNormalizeConsole();
        $console->setFacade($facadeMock);
        $console->setFactory($this->createFactoryMock($this->createMock(SearchRankingToSearchRankingStorageFacadeInterface::class)));

        $application = new Application();
        $application->add($console);
        $commandTester = new CommandTester($application->find(SearchRankingNormalizeConsole::COMMAND_NAME));

        // Act
        $exitCode = $commandTester->execute(['--store' => 'DE', '--locale' => 'de_DE']);

        // Assert
        $this->assertSame(SearchRankingNormalizeConsole::CODE_SUCCESS, $exitCode);
    }

    /**
     * @param int $updatedRowCount
     * @param int $processedMetricCount
     * @param array<string> $errors
     */
    protected function createResultTransfer(int $updatedRowCount, int $processedMetricCount, array $errors): SearchRankingNormalizationResultTransfer
    {
        return (new SearchRankingNormalizationResultTransfer())
            ->setUpdatedRowCountOrFail($updatedRowCount)
            ->setProcessedMetricCountOrFail($processedMetricCount)
            ->setErrors($errors);
    }

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSearchRankingStorageFacadeInterface $storageFacadeMock
     */
    protected function createFactoryMock(SearchRankingToSearchRankingStorageFacadeInterface $storageFacadeMock): SearchRankingCommunicationFactory
    {
        $factoryMock = $this->getMockBuilder(SearchRankingCommunicationFactory::class)
            ->onlyMethods(['getSearchRankingStorageFacade'])
            ->getMock();
        $factoryMock->method('getSearchRankingStorageFacade')->willReturn($storageFacadeMock);

        return $factoryMock;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer $resultTransfer
     * @param int $publishedProductCount
     * @param int $digestCount
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToSearchRankingStorageFacadeInterface $storageFacadeMock
     */
    protected function createCommandTester(
        SearchRankingNormalizationResultTransfer $resultTransfer,
        int $publishedProductCount,
        int $digestCount,
        SearchRankingToSearchRankingStorageFacadeInterface $storageFacadeMock,
    ): CommandTester {
        $facadeMock = $this->getMockBuilder(SearchRankingFacade::class)
            ->onlyMethods(['normalizeProductMetricValues', 'rebuildMetricDigests', 'publishScoredProductAbstracts'])
            ->getMock();
        $facadeMock->method('normalizeProductMetricValues')->willReturn($resultTransfer);
        $facadeMock->method('rebuildMetricDigests')->willReturn($digestCount);
        $facadeMock->method('publishScoredProductAbstracts')->willReturn($publishedProductCount);

        $console = new SearchRankingNormalizeConsole();
        $console->setFacade($facadeMock);
        $console->setFactory($this->createFactoryMock($storageFacadeMock));

        $application = new Application();
        $application->add($console);

        return new CommandTester($application->find(SearchRankingNormalizeConsole::COMMAND_NAME));
    }
}
