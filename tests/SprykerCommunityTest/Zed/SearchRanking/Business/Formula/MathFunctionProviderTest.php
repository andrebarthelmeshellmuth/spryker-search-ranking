<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Formula;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\MathFunctionProvider;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

/**
 * `random()`'s EVALUATOR closure (used by every real `->evaluate()` call, e.g. via FormulaEvaluator) is
 * covered indirectly through FormulaEvaluatorTest — but `ExpressionFunction` requires BOTH a compiler and
 * an evaluator closure, and nothing in this codebase ever calls ExpressionLanguage's `compile()` (only
 * `evaluate()`), so the COMPILER closure is otherwise never exercised at all. Tested directly here rather
 * than left uncovered, since it's a real, reachable code path — just one this package's own call sites
 * happen not to use yet.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Formula
 * @group MathFunctionProviderTest
 * Add your own group annotations below this line
 * @group Portable
 */
class MathFunctionProviderTest extends Unit
{
    public function testRandomFunctionCompilerProducesPhpSourceEquivalentToItsEvaluator(): void
    {
        // Arrange
        $randomFunction = $this->findRandomFunction();

        // Act
        $compiledSource = ($randomFunction->getCompiler())();

        // Assert
        $this->assertSame('(1.0 - mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax())', $compiledSource);
    }

    public function testRandomFunctionEvaluatorStaysWithinHalfOpenUnitInterval(): void
    {
        // Arrange
        $randomFunction = $this->findRandomFunction();

        for ($i = 0; $i < 100; $i++) {
            // Act
            $result = ($randomFunction->getEvaluator())();

            // Assert
            $this->assertGreaterThan(0.0, $result);
            $this->assertLessThanOrEqual(1.0, $result);
        }
    }

    protected function findRandomFunction(): ExpressionFunction
    {
        foreach ((new MathFunctionProvider())->getFunctions() as $function) {
            if ($function->getName() === 'random') {
                return $function;
            }
        }

        $this->fail('MathFunctionProvider does not register a "random" function.');
    }
}
