<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Strategy;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingQueryContextTransfer;
use SprykerCommunity\Client\SearchRanking\Query\FunctionScoreBuilder;
use SprykerCommunity\Client\SearchRanking\SearchRankingFactory;
use SprykerCommunity\Client\SearchRanking\Strategy\AdaptiveFormulaStrategy;
use SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyExecutionMode;
use SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Strategy
 * @group RankingStrategyResolutionTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchRanking\SearchRankingClientTester $tester
 */
class RankingStrategyResolutionTest extends Unit
{
    /**
     * With no project strategy registered, {@see AdaptiveFormulaStrategy} is always the resolved
     * strategy — the pre-Phase-3 behavior.
     */
    public function testResolvesTheAdaptiveFormulaStrategyWhenNoProjectStrategyIsRegistered(): void
    {
        // Arrange
        $factory = $this->createFactory([]);

        // Act
        $rankingStrategy = $factory->resolveRankingStrategy(new SearchRankingQueryContextTransfer());

        // Assert
        $this->assertInstanceOf(AdaptiveFormulaStrategy::class, $rankingStrategy);
        $this->assertSame(RankingStrategyExecutionMode::MODE_BODY_ONLY, $rankingStrategy->getExecutionMode());
    }

    /**
     * The first registered project strategy whose `supports()` is `true` wins over the always-`true`
     * {@see AdaptiveFormulaStrategy} fallback, even though the fallback is listed first in the stack.
     */
    public function testResolvesTheFirstSupportingProjectStrategyOverTheFallback(): void
    {
        // Arrange
        $notSupportingStrategy = $this->createStrategyMock('brand_boost', false, RankingStrategyExecutionMode::MODE_BODY_ONLY);
        $supportingStrategy = $this->createStrategyMock('neural_rerank', true, RankingStrategyExecutionMode::MODE_OUT_OF_BAND);
        $laterSupportingStrategy = $this->createStrategyMock('never_reached', true, RankingStrategyExecutionMode::MODE_BODY_ONLY);

        $factory = $this->createFactory([$notSupportingStrategy, $supportingStrategy, $laterSupportingStrategy]);

        // Act
        $rankingStrategy = $factory->resolveRankingStrategy(new SearchRankingQueryContextTransfer());

        // Assert
        $this->assertSame($supportingStrategy, $rankingStrategy);
    }

    /**
     * When no project strategy supports the query, resolution falls back to
     * {@see AdaptiveFormulaStrategy}, regardless of how many non-supporting project strategies precede it.
     */
    public function testFallsBackToTheAdaptiveFormulaStrategyWhenNoProjectStrategySupportsTheQuery(): void
    {
        // Arrange
        $notSupportingStrategy = $this->createStrategyMock('brand_boost', false, RankingStrategyExecutionMode::MODE_BODY_ONLY);

        $factory = $this->createFactory([$notSupportingStrategy]);

        // Act
        $rankingStrategy = $factory->resolveRankingStrategy(new SearchRankingQueryContextTransfer());

        // Assert
        $this->assertInstanceOf(AdaptiveFormulaStrategy::class, $rankingStrategy);
    }

    /**
     * @param array<\SprykerCommunity\Client\SearchRanking\Strategy\RankingStrategyInterface> $rankingStrategyPlugins
     */
    protected function createFactory(array $rankingStrategyPlugins): SearchRankingFactory
    {
        $factoryMock = $this->getMockBuilder(SearchRankingFactory::class)
            ->onlyMethods(['getRankingStrategyPlugins', 'createFunctionScoreBuilder'])
            ->getMock();
        $factoryMock->method('getRankingStrategyPlugins')->willReturn($rankingStrategyPlugins);
        $factoryMock->method('createFunctionScoreBuilder')->willReturn(new FunctionScoreBuilder());

        return $factoryMock;
    }

    /**
     * @param string $name
     * @param bool $supports
     * @param string $executionMode
     */
    protected function createStrategyMock(string $name, bool $supports, string $executionMode): RankingStrategyInterface
    {
        $strategyMock = $this->createMock(RankingStrategyInterface::class);
        $strategyMock->method('getName')->willReturn($name);
        $strategyMock->method('supports')->willReturn($supports);
        $strategyMock->method('getExecutionMode')->willReturn($executionMode);

        return $strategyMock;
    }
}
