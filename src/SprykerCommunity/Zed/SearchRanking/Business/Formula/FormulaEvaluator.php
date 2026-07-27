<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Formula;

use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\FormulaEvaluationException;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

class FormulaEvaluator implements FormulaEvaluatorInterface
{
    /**
     * @var \Symfony\Component\ExpressionLanguage\ExpressionLanguage
     */
    protected ExpressionLanguage $expressionLanguage;

    /**
     * @param \Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface $mathFunctionProvider
     * @param \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig $config
     */
    public function __construct(ExpressionFunctionProviderInterface $mathFunctionProvider, protected SearchRankingConfig $config)
    {
        $this->expressionLanguage = new ExpressionLanguage(null, [$mathFunctionProvider]);
    }

    /**
     * @param string $formula
     * @param array<string, float|int> $variables
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\FormulaEvaluationException
     *
     * @return float
     */
    public function evaluate(string $formula, array $variables): float
    {
        try {
            $result = $this->expressionLanguage->evaluate($formula, $variables);
        } catch (Throwable $throwable) {
            throw new FormulaEvaluationException(
                sprintf('Formula "%s" failed to evaluate: %s', $formula, $throwable->getMessage()),
                0,
                $throwable,
            );
        }

        if (!is_int($result) && !is_float($result)) {
            throw new FormulaEvaluationException(
                sprintf('Formula "%s" evaluated to a non-numeric result of type %s.', $formula, get_debug_type($result)),
            );
        }

        $result = (float)$result;

        if (!is_finite($result)) {
            throw new FormulaEvaluationException(
                sprintf('Formula "%s" evaluated to a non-finite number.', $formula),
            );
        }

        return $result;
    }

    /**
     * @param string $formula
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer
     */
    public function validate(string $formula): SearchRankingFormulaValidationResponseTransfer
    {
        $responseTransfer = new SearchRankingFormulaValidationResponseTransfer();

        try {
            $this->evaluate($formula, $this->config->getFormulaValidationVariables());
        } catch (Throwable $throwable) {
            return $responseTransfer
                ->setIsSuccess(false)
                ->setErrorMessage($throwable->getMessage());
        }

        return $responseTransfer->setIsSuccess(true);
    }
}
