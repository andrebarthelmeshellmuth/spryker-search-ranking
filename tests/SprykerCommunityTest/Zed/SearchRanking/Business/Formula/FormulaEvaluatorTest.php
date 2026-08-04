<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\Formula;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\FormulaEvaluationException;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluator;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\MathFunctionProvider;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group Formula
 * @group FormulaEvaluatorTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Zed\SearchRanking\SearchRankingZedTester $tester
 */
class FormulaEvaluatorTest extends Unit
{
    public function testEvaluatesArcTangentNormalizationFormula(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        // Act
        $result = $formulaEvaluator->evaluate('atan(x / avg) / (pi() / 2)', [
            'x' => 3500.0,
            'min' => 0.0,
            'max' => 50000.0,
            'avg' => 3500.0,
            'count' => 451,
        ]);

        // Assert: atan(1) / (pi/2) = (pi/4) / (pi/2) = 0.5
        $this->assertEqualsWithDelta(0.5, $result, 1.0E-9);
    }

    public function testEvaluatesMaxScalingFormula(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        // Act
        $result = $formulaEvaluator->evaluate('x / max', [
            'x' => 250.0,
            'min' => 0.0,
            'max' => 1000.0,
            'avg' => 120.0,
            'count' => 10,
        ]);

        // Assert
        $this->assertSame(0.25, $result);
    }

    /**
     * random() must return a value in ]0;1] and must not require any variables.
     */
    public function testRandomFunctionStaysWithinHalfOpenUnitInterval(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        for ($i = 0; $i < 100; $i++) {
            // Act
            $result = $formulaEvaluator->evaluate('random()', []);

            // Assert
            $this->assertGreaterThan(0.0, $result);
            $this->assertLessThanOrEqual(1.0, $result);
        }
    }

    public function testThrowsWhenFormulaDividesByZero(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        // Assert
        $this->expectException(FormulaEvaluationException::class);

        // Act
        $formulaEvaluator->evaluate('x / max', [
            'x' => 1.0,
            'min' => 0.0,
            'max' => 0.0,
            'avg' => 0.0,
            'count' => 3,
        ]);
    }

    public function testThrowsWhenFormulaUsesUnknownFunction(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        // Assert
        $this->expectException(FormulaEvaluationException::class);

        // Act
        $formulaEvaluator->evaluate('sigmoid(x)', ['x' => 1.0]);
    }

    /**
     * A syntactically valid expression that evaluates cleanly (no Throwable) to a non-numeric PHP value
     * must still be rejected — a metric weight downstream expects a float, not a string.
     */
    public function testThrowsWhenFormulaEvaluatesToANonNumericResult(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        // Assert
        $this->expectException(FormulaEvaluationException::class);
        $this->expectExceptionMessage('evaluated to a non-numeric result');

        // Act
        $formulaEvaluator->evaluate('"just a string"', []);
    }

    /**
     * Float overflow (unlike division by zero) raises no PHP warning at all — it silently produces INF —
     * so this exercises the dedicated `is_finite()` guard rather than the try/catch around evaluation.
     */
    public function testThrowsWhenFormulaEvaluatesToANonFiniteResult(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        // Assert
        $this->expectException(FormulaEvaluationException::class);
        $this->expectExceptionMessage('evaluated to a non-finite number');

        // Act
        $formulaEvaluator->evaluate('1.0e308 * 10', []);
    }

    public function testValidateAcceptsWellFormedFormula(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        // Act
        $responseTransfer = $formulaEvaluator->validate('tanh(x / (avg + 1))');

        // Assert
        $this->assertTrue($responseTransfer->getIsSuccess());
        $this->assertNull($responseTransfer->getErrorMessage());
    }

    public function testValidateReportsSyntaxErrorWithMessage(): void
    {
        // Arrange
        $formulaEvaluator = $this->createFormulaEvaluator();

        // Act
        $responseTransfer = $formulaEvaluator->validate('atan(x');

        // Assert
        $this->assertFalse($responseTransfer->getIsSuccess());
        $this->assertNotEmpty($responseTransfer->getErrorMessage());
    }

    protected function createFormulaEvaluator(): FormulaEvaluator
    {
        return new FormulaEvaluator(new MathFunctionProvider(), new SearchRankingConfig());
    }
}
