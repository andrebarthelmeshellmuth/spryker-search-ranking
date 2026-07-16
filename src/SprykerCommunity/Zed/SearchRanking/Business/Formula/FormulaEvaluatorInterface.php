<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Formula;

use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;

interface FormulaEvaluatorInterface
{
    /**
     * @param string $formula
     * @param array<string, float|int> $variables
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\FormulaEvaluationException
     *
     * @return float
     */
    public function evaluate(string $formula, array $variables): float;

    /**
     * @param string $formula
     *
     * @return \Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer
     */
    public function validate(string $formula): SearchRankingFormulaValidationResponseTransfer;
}
