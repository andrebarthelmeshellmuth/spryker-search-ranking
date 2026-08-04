<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Formula;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Registers the math vocabulary available inside normalization formulas. Everything except
 * random() delegates to the native PHP function of the same name.
 */
class MathFunctionProvider implements ExpressionFunctionProviderInterface
{
    /**
     * @return array<\Symfony\Component\ExpressionLanguage\ExpressionFunction>
     */
    public function getFunctions(): array
    {
        return [
            ExpressionFunction::fromPhp('atan'),
            ExpressionFunction::fromPhp('tanh'),
            ExpressionFunction::fromPhp('log'),
            ExpressionFunction::fromPhp('log10'),
            ExpressionFunction::fromPhp('sqrt'),
            ExpressionFunction::fromPhp('exp'),
            ExpressionFunction::fromPhp('abs'),
            ExpressionFunction::fromPhp('pi'),
            ExpressionFunction::fromPhp('pow'),
            ExpressionFunction::fromPhp('round'),
            ExpressionFunction::fromPhp('min'),
            ExpressionFunction::fromPhp('max'),
            $this->createRandomFunction(),
        ];
    }

    /**
     * random() returns a uniformly distributed value in ]0;1] on every call.
     */
    protected function createRandomFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'random',
            static fn (): string => '(1.0 - mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax())',
            static fn (): float => 1.0 - mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(),
        );
    }
}
