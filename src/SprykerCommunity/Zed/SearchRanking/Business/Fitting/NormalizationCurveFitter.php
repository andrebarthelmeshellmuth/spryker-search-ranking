<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Fitting;

use Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;

/**
 * The "interesting part" of the normalization-authoring GUI (phase 4.5): a normalization formula's job is
 * to DISCRIMINATE — spread products across ]0;1] — not merely map into range. The empirical CDF (map each
 * raw value to the fraction of products below it) is the theoretical max-discrimination normalizer, so it
 * is the reference every candidate is scored against: whichever closed-form curve tracks that CDF most
 * closely is the best data-driven suggestion, distribution-agnostic by construction — no shape gets
 * special status, the data picks.
 *
 * Each candidate family has exactly one free scale/shape parameter, found by a coarse-to-fine geometric
 * (log-spaced) search minimizing squared error against the 101-point digest — deliberately not a general
 * optimizer dependency, since a single bounded parameter search this small does not need one.
 */
class NormalizationCurveFitter implements NormalizationCurveFitterInterface
{
    /**
     * @var int
     */
    protected const GRID_STEP_COUNT = 40;

    /**
     * @var int
     */
    protected const REFINEMENT_PASS_COUNT = 6;

    /**
     * {@inheritDoc}
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     * @param bool $isHigherBetter
     *
     * @return array<\Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer>
     */
    public function fit(SearchRankingMetricDigestTransfer $digestTransfer, bool $isHigherBetter): array
    {
        $points = $this->buildFitPoints($digestTransfer);
        $candidateTransfers = [];

        foreach ($this->buildCandidateDefinitions($digestTransfer, $isHigherBetter) as $definition) {
            $candidateTransfer = $this->fitCandidate($definition, $points);

            if ($candidateTransfer === null) {
                continue;
            }

            $candidateTransfers[] = $candidateTransfer;
        }

        usort(
            $candidateTransfers,
            fn (SearchRankingCurveFitCandidateTransfer $a, SearchRankingCurveFitCandidateTransfer $b): int => $b->getRSquaredOrFail() <=> $a->getRSquaredOrFail(),
        );

        if ($candidateTransfers !== []) {
            $candidateTransfers[0]->setIsWinner(true);
        }

        return $candidateTransfers;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     *
     * @return array<int, array{x: float, y: float}>
     */
    protected function buildFitPoints(SearchRankingMetricDigestTransfer $digestTransfer): array
    {
        $percentiles = $digestTransfer->getPercentiles();
        $lastIndex = count($percentiles) - 1;
        $points = [];

        foreach ($percentiles as $index => $value) {
            $points[] = ['x' => $value, 'y' => $index / $lastIndex];
        }

        return $points;
    }

    /**
     * One entry per candidate shape: `name` is the human-readable label, `formulaTemplate` builds the
     * concrete, pastable expression-language string from the fitted parameter (or null for the
     * zero-parameter linear candidate), `curveFunction` is `fn(x, param): float` used both for fitting
     * and (implicitly, via the same math) for what the formula string evaluates to, and the two log-space
     * bounds constrain the 1D parameter search. `paramLogMin`/`paramLogMax` are null for the zero-parameter
     * linear candidate, which is scored once with no search.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricDigestTransfer $digestTransfer
     * @param bool $isHigherBetter
     *
     * @return array<int, array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null}>
     */
    protected function buildCandidateDefinitions(SearchRankingMetricDigestTransfer $digestTransfer, bool $isHigherBetter): array
    {
        $minValue = $digestTransfer->getMinValueOrFail();
        $maxValue = $digestTransfer->getMaxValueOrFail();
        $meanValue = $digestTransfer->getMeanValueOrFail();
        $medianValue = $digestTransfer->getMedianValueOrFail();
        $percentiles = $digestTransfer->getPercentiles();

        $definitions = [$this->buildLinearDefinition($minValue, $maxValue, $isHigherBetter)];

        // Scale-parameter search range: wide enough to span orders of magnitude around the metric's own
        // values, since a good k/tau can legitimately be much smaller or larger than the mean/median
        // depending on how sharply the data saturates. Anchored on the median and the interquartile
        // range rather than (max - min) — max is, by definition, the single most extreme observed value,
        // so a real long-tailed metric's own outliers would otherwise balloon the search range far past
        // where the true best parameter actually sits (a bestseller with 100x the typical impressions
        // must not drag the whole search away from the scale the other 99% of products live at).
        $interquartileRange = ($percentiles[75] ?? $medianValue) - ($percentiles[25] ?? $medianValue);
        $scaleReference = max($medianValue, $interquartileRange, 1.0);
        $scaleLogMin = log(max($scaleReference / 1.0E5, 1.0E-6));
        $scaleLogMax = log($scaleReference * 1.0E5);

        $definitions[] = $this->buildAtanDefinition($scaleLogMin, $scaleLogMax, $isHigherBetter);
        $definitions[] = $this->buildSigmoidDefinition($meanValue, $scaleLogMin, $scaleLogMax, $isHigherBetter);

        // pow() exponent is a shape parameter, not a scale one — a fixed, family-appropriate range.
        $exponentLogMin = log(0.05);
        $exponentLogMax = log(20.0);

        if ($maxValue > 0.0) {
            $definitions[] = $this->buildPowerDefinition($maxValue, $exponentLogMin, $exponentLogMax, $isHigherBetter);
            $definitions[] = $this->buildLogDefinition($maxValue, $scaleLogMin, $scaleLogMax, $isHigherBetter);
        }

        if ($isHigherBetter) {
            $definitions[] = $this->buildSaturatingRatioDefinition($scaleLogMin, $scaleLogMax);
        } else {
            $definitions[] = $this->buildDecayDefinition($scaleLogMin, $scaleLogMax);
        }

        return $definitions;
    }

    /**
     * @param float $minValue
     * @param float $maxValue
     * @param bool $isHigherBetter
     *
     * @return array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null}
     */
    protected function buildLinearDefinition(float $minValue, float $maxValue, bool $isHigherBetter): array
    {
        // The linear family has no free parameter, but every curveFunction is called uniformly as
        // fn(x, param) — $param is always passed (0.0 here), so the signature must accept it even though
        // this one shape ignores it.
        if ($isHigherBetter) {
            return [
                'name' => '(x - min) / (max - min)',
                'formulaTemplate' => fn (): string => '(x - min) / (max - min)',
                /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter */
                'curveFunction' => fn (float $x, float $param): float => $maxValue > $minValue ? ($x - $minValue) / ($maxValue - $minValue) : 0.5,
                'paramLogMin' => null,
                'paramLogMax' => null,
            ];
        }

        return [
            'name' => '(max - x) / (max - min)',
            'formulaTemplate' => fn (): string => '(max - x) / (max - min)',
            /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter */
            'curveFunction' => fn (float $x, float $param): float => $maxValue > $minValue ? ($maxValue - $x) / ($maxValue - $minValue) : 0.5,
            'paramLogMin' => null,
            'paramLogMax' => null,
        ];
    }

    /**
     * @param float $logMin
     * @param float $logMax
     * @param bool $isHigherBetter
     *
     * @return array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null}
     */
    protected function buildAtanDefinition(float $logMin, float $logMax, bool $isHigherBetter): array
    {
        if ($isHigherBetter) {
            return [
                'name' => 'atan(x / k) / (pi / 2)',
                'formulaTemplate' => fn (float $k): string => sprintf('atan(x / %s) / (pi() / 2)', $this->formatParameter($k)),
                'curveFunction' => fn (float $x, float $k): float => atan($x / $k) / (M_PI / 2),
                'paramLogMin' => $logMin,
                'paramLogMax' => $logMax,
            ];
        }

        return [
            'name' => '1 - atan(x / k) / (pi / 2)',
            'formulaTemplate' => fn (float $k): string => sprintf('1 - atan(x / %s) / (pi() / 2)', $this->formatParameter($k)),
            'curveFunction' => fn (float $x, float $k): float => 1 - atan($x / $k) / (M_PI / 2),
            'paramLogMin' => $logMin,
            'paramLogMax' => $logMax,
        ];
    }

    /**
     * @param float $meanValue
     * @param float $logMin
     * @param float $logMax
     * @param bool $isHigherBetter
     *
     * @return array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null}
     */
    protected function buildSigmoidDefinition(float $meanValue, float $logMin, float $logMax, bool $isHigherBetter): array
    {
        $sigmoid = fn (float $x, float $k): float => 1 / (1 + exp(-($x - $meanValue) / $k));

        if ($isHigherBetter) {
            return [
                'name' => '1 / (1 + exp(-(x - avg) / k))',
                'formulaTemplate' => fn (float $k): string => sprintf('1 / (1 + exp(-(x - avg) / %s))', $this->formatParameter($k)),
                'curveFunction' => $sigmoid,
                'paramLogMin' => $logMin,
                'paramLogMax' => $logMax,
            ];
        }

        return [
            'name' => '1 - 1 / (1 + exp(-(x - avg) / k))',
            'formulaTemplate' => fn (float $k): string => sprintf('1 - 1 / (1 + exp(-(x - avg) / %s))', $this->formatParameter($k)),
            'curveFunction' => fn (float $x, float $k): float => 1 - $sigmoid($x, $k),
            'paramLogMin' => $logMin,
            'paramLogMax' => $logMax,
        ];
    }

    /**
     * Generalizes sqrt (p = 0.5): p < 1 is concave/diminishing-returns, p > 1 is convex. Guarded by the
     * caller to only run when maxValue > 0 — (x / max)^p is undefined as a normalizer otherwise.
     *
     * @param float $maxValue
     * @param float $logMin
     * @param float $logMax
     * @param bool $isHigherBetter
     *
     * @return array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null}
     */
    protected function buildPowerDefinition(float $maxValue, float $logMin, float $logMax, bool $isHigherBetter): array
    {
        $power = fn (float $x, float $p): float => pow(max($x, 0.0) / $maxValue, $p);

        if ($isHigherBetter) {
            return [
                'name' => 'pow(x / max, p)',
                'formulaTemplate' => fn (float $p): string => sprintf('pow(x / max, %s)', $this->formatParameter($p)),
                'curveFunction' => $power,
                'paramLogMin' => $logMin,
                'paramLogMax' => $logMax,
            ];
        }

        return [
            'name' => '1 - pow(x / max, p)',
            'formulaTemplate' => fn (float $p): string => sprintf('1 - pow(x / max, %s)', $this->formatParameter($p)),
            'curveFunction' => fn (float $x, float $p): float => 1 - $power($x, $p),
            'paramLogMin' => $logMin,
            'paramLogMax' => $logMax,
        ];
    }

    /**
     * Unlike atan/sigmoid, `log(1 + u)` has no finite horizontal asymptote, so it is normalized against
     * the digest's own observed max (reaches exactly 1 there) rather than an assumed limit — a genuinely
     * different saturation behaviour from the atan/ratio families: aggressive early compression of a wide
     * range, tied to what has actually been observed rather than an unbounded future. Guarded by the
     * caller to only run when maxValue > 0.
     *
     * @param float $maxValue
     * @param float $logMin
     * @param float $logMax
     * @param bool $isHigherBetter
     *
     * @return array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null}
     */
    protected function buildLogDefinition(float $maxValue, float $logMin, float $logMax, bool $isHigherBetter): array
    {
        $log = function (float $x, float $k) use ($maxValue): float {
            $numerator = log(1 + max($x, 0.0) / $k);
            $denominator = log(1 + $maxValue / $k);

            return $denominator > 0.0 ? $numerator / $denominator : 0.0;
        };

        if ($isHigherBetter) {
            return [
                'name' => 'log(1 + x / k) / log(1 + max / k)',
                'formulaTemplate' => fn (float $k): string => sprintf('log(1 + x / %1$s) / log(1 + max / %1$s)', $this->formatParameter($k)),
                'curveFunction' => $log,
                'paramLogMin' => $logMin,
                'paramLogMax' => $logMax,
            ];
        }

        return [
            'name' => '1 - log(1 + x / k) / log(1 + max / k)',
            'formulaTemplate' => fn (float $k): string => sprintf('1 - log(1 + x / %1$s) / log(1 + max / %1$s)', $this->formatParameter($k)),
            'curveFunction' => fn (float $x, float $k): float => 1 - $log($x, $k),
            'paramLogMin' => $logMin,
            'paramLogMax' => $logMax,
        ];
    }

    /**
     * `x / (x + k)` — the same saturating shape `relevanceSaturationPoint` already uses elsewhere in this
     * package. isHigherBetter-only: {@see buildDecayDefinition()} is the decreasing counterpart offered
     * instead of a bare inversion of this one, per the roadmap's own explicit example.
     *
     * @param float $logMin
     * @param float $logMax
     *
     * @return array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null}
     */
    protected function buildSaturatingRatioDefinition(float $logMin, float $logMax): array
    {
        return [
            'name' => 'x / (x + k)',
            'formulaTemplate' => fn (float $k): string => sprintf('x / (x + %s)', $this->formatParameter($k)),
            'curveFunction' => fn (float $x, float $k): float => max($x, 0.0) / (max($x, 0.0) + $k),
            'paramLogMin' => $logMin,
            'paramLogMax' => $logMax,
        ];
    }

    /**
     * `exp(-x / tau)` — "less/newer is better" (e.g. days-since-restock, return rate). Offered only when
     * NOT isHigherBetter, in place of {@see buildSaturatingRatioDefinition()}, per the roadmap's own
     * example of the decreasing-signal case.
     *
     * @param float $logMin
     * @param float $logMax
     *
     * @return array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null}
     */
    protected function buildDecayDefinition(float $logMin, float $logMax): array
    {
        return [
            'name' => 'exp(-x / tau)',
            'formulaTemplate' => fn (float $tau): string => sprintf('exp(-x / %s)', $this->formatParameter($tau)),
            'curveFunction' => fn (float $x, float $tau): float => exp(-max($x, 0.0) / $tau),
            'paramLogMin' => $logMin,
            'paramLogMax' => $logMax,
        ];
    }

    // phpcs:disable Spryker.Commenting.DocBlockParamAllowDefaultValue.Typehint -- misreads this shaped array docblock as a default-value typehint check

    /**
     * @param array{name: string, formulaTemplate: callable, curveFunction: callable, paramLogMin: float|null, paramLogMax: float|null} $definition
     * @param array<int, array{x: float, y: float}> $points
     *
     * @return \Generated\Shared\Transfer\SearchRankingCurveFitCandidateTransfer|null
     */
    protected function fitCandidate(array $definition, array $points): ?SearchRankingCurveFitCandidateTransfer
    {
        $curveFunction = $definition['curveFunction'];

        if ($definition['paramLogMin'] === null) {
            $rSquared = $this->rSquared($points, $curveFunction, 0.0);

            if ($rSquared === null) {
                return null;
            }

            return (new SearchRankingCurveFitCandidateTransfer())
                ->setName($definition['name'])
                ->setFormula(($definition['formulaTemplate'])())
                ->setParameterValue(0.0)
                ->setRSquared($rSquared)
                ->setIsWinner(false);
        }

        $searchResult = $this->searchParameter($curveFunction, $definition['paramLogMin'], $definition['paramLogMax'], $points);

        if ($searchResult === null) {
            return null;
        }

        return (new SearchRankingCurveFitCandidateTransfer())
            ->setName($definition['name'])
            ->setFormula(($definition['formulaTemplate'])($searchResult['param']))
            ->setParameterValue($searchResult['param'])
            ->setRSquared($searchResult['rSquared'])
            ->setIsWinner(false);
    }

    // phpcs:enable Spryker.Commenting.DocBlockParamAllowDefaultValue.Typehint

    /**
     * Coarse geometric grid search over the log-space bounds, then a few halving local-refinement passes
     * around the best grid point — enough precision for a suggestion without a general optimizer
     * dependency for what is always exactly one bounded scalar parameter.
     *
     * @param callable $curveFunction fn(float $x, float $param): float
     * @param float $logMin
     * @param float $logMax
     * @param array<int, array{x: float, y: float}> $points
     *
     * @return array{param: float, rSquared: float}|null
     */
    protected function searchParameter(callable $curveFunction, float $logMin, float $logMax, array $points): ?array
    {
        $bestLogParam = null;
        $bestRSquared = -INF;

        for ($step = 0; $step <= static::GRID_STEP_COUNT; $step++) {
            $logParam = $logMin + ($logMax - $logMin) * $step / static::GRID_STEP_COUNT;
            $rSquared = $this->rSquared($points, $curveFunction, exp($logParam));

            if ($rSquared === null || $rSquared <= $bestRSquared) {
                continue;
            }

            $bestRSquared = $rSquared;
            $bestLogParam = $logParam;
        }

        if ($bestLogParam === null) {
            return null;
        }

        $refinementRadius = ($logMax - $logMin) / static::GRID_STEP_COUNT;

        for ($pass = 0; $pass < static::REFINEMENT_PASS_COUNT; $pass++) {
            $refinementRadius /= 2;

            foreach ([-1, 1] as $direction) {
                $candidateLogParam = $bestLogParam + $direction * $refinementRadius;
                $rSquared = $this->rSquared($points, $curveFunction, exp($candidateLogParam));

                if ($rSquared === null || $rSquared <= $bestRSquared) {
                    continue;
                }

                $bestRSquared = $rSquared;
                $bestLogParam = $candidateLogParam;
            }
        }

        return ['param' => exp($bestLogParam), 'rSquared' => $bestRSquared];
    }

    /**
     * Standard R² = 1 - SS_res / SS_tot. Returns null (rather than NAN/INF) when the curve produces a
     * non-finite value anywhere — an out-of-domain parameter is simply excluded from the search/ranking,
     * never allowed to poison it.
     *
     * @param array<int, array{x: float, y: float}> $points
     * @param callable $curveFunction fn(float $x, float $param): float
     * @param float $param
     *
     * @return float|null
     */
    protected function rSquared(array $points, callable $curveFunction, float $param): ?float
    {
        $meanY = array_sum(array_column($points, 'y')) / count($points);
        $sumSquaredResiduals = 0.0;
        $sumSquaredTotal = 0.0;

        foreach ($points as $point) {
            $predicted = $curveFunction($point['x'], $param);

            if (!is_finite($predicted)) {
                return null;
            }

            $sumSquaredResiduals += ($point['y'] - $predicted) ** 2;
            $sumSquaredTotal += ($point['y'] - $meanY) ** 2;
        }

        if ($sumSquaredTotal === 0.0) {
            return $sumSquaredResiduals === 0.0 ? 1.0 : 0.0;
        }

        return 1 - $sumSquaredResiduals / $sumSquaredTotal;
    }

    /**
     * @param float $parameter
     *
     * @return string
     */
    protected function formatParameter(float $parameter): string
    {
        return rtrim(rtrim(sprintf('%.6F', $parameter), '0'), '.');
    }
}
