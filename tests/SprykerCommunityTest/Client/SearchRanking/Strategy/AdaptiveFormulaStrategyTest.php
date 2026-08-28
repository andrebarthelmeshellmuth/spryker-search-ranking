<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Strategy;

use Codeception\Test\Unit;
use Elastica\Query\BoolQuery;
use Elastica\Query\FunctionScore;
use Elastica\Query\MatchAll;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilderInterface;
use SprykerCommunity\Client\SearchRanking\Strategy\AdaptiveFormulaStrategy;
use SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyExecutionMode;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Strategy
 * @group AdaptiveFormulaStrategyTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRanking\SearchRankingClientTester $tester
 */
class AdaptiveFormulaStrategyTest extends Unit
{
    /**
     * The optimizer keys its parameter space on this exact string — it must stay stable.
     */
    public function testGetNameReturnsTheStableIdentifier(): void
    {
        // Arrange
        $strategy = new AdaptiveFormulaStrategy($this->createMock(FunctionScoreBuilderInterface::class));

        // Act & Assert
        $this->assertSame('adaptive_formula', $strategy->getName());
        $this->assertSame(AdaptiveFormulaStrategy::NAME, $strategy->getName());
    }

    public function testSupportsEveryQueryContextUnconditionally(): void
    {
        // Arrange
        $strategy = new AdaptiveFormulaStrategy($this->createMock(FunctionScoreBuilderInterface::class));

        // Act & Assert
        $this->assertTrue($strategy->supports(new SearchRankingQueryContextTransfer()));
        $this->assertTrue(
            $strategy->supports(
                (new SearchRankingQueryContextTransfer())->setSearchString('gadget')->setIsIdentifierMatch(true),
            ),
        );
    }

    public function testDeclaresItselfBodyOnly(): void
    {
        // Arrange
        $strategy = new AdaptiveFormulaStrategy($this->createMock(FunctionScoreBuilderInterface::class));

        // Act & Assert
        $this->assertSame(RankingStrategyExecutionMode::MODE_BODY_ONLY, $strategy->getExecutionMode());
    }

    /**
     * The wrapper must hand the exact arguments it received straight to `FunctionScoreBuilder::build()`
     * and return its result unchanged — that verbatim delegation is what preserves byte-identical query
     * output with only this strategy registered.
     */
    public function testBuildDelegatesVerbatimToTheFunctionScoreBuilder(): void
    {
        // Arrange
        $wrappedQuery = new BoolQuery();
        $configurationTransfer = (new SearchRankingConfigurationStorageTransfer())->setRelevanceWeight(0.6);
        $queryVector = [0.1, -0.2, 0.3];
        $queryContextTransfer = (new SearchRankingQueryContextTransfer())->setSearchString('gadget');
        $functionScore = new FunctionScore();

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with($wrappedQuery, $configurationTransfer, $queryVector, $queryContextTransfer)
            ->willReturn($functionScore);

        $strategy = new AdaptiveFormulaStrategy($functionScoreBuilderMock);

        // Act
        $result = $strategy->build($wrappedQuery, $configurationTransfer, $queryVector, $queryContextTransfer);

        // Assert
        $this->assertSame($functionScore, $result);
    }

    public function testBuildReturnsNullWhenTheFunctionScoreBuilderReturnsNull(): void
    {
        // Arrange
        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->method('build')->willReturn(null);

        $strategy = new AdaptiveFormulaStrategy($functionScoreBuilderMock);

        // Act
        $result = $strategy->build(new MatchAll(), new SearchRankingConfigurationStorageTransfer());

        // Assert
        $this->assertNull($result);
    }

    /**
     * The optional third/fourth arguments default exactly as `FunctionScoreBuilderInterface::build()`
     * does — a two-argument call must reach the builder as `(query, config, null, null)`.
     */
    public function testBuildPassesTheDefaultedOptionalArgumentsThrough(): void
    {
        // Arrange
        $wrappedQuery = new MatchAll();
        $configurationTransfer = new SearchRankingConfigurationStorageTransfer();

        $functionScoreBuilderMock = $this->createMock(FunctionScoreBuilderInterface::class);
        $functionScoreBuilderMock->expects($this->once())
            ->method('build')
            ->with($wrappedQuery, $configurationTransfer, null, null)
            ->willReturn(null);

        $strategy = new AdaptiveFormulaStrategy($functionScoreBuilderMock);

        // Act
        $strategy->build($wrappedQuery, $configurationTransfer);
    }
}
