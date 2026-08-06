<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

class SpecificityWeightCalculator implements SpecificityWeightCalculatorInterface
{
    /**
     * @param \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyFetcherInterface $queryTermFrequencyFetcher
     * @param \SprykerCommunity\Client\SearchRanking\Search\QuerySpecificityCalculatorInterface $querySpecificityCalculator
     * @param array<string, string> $fieldToSearchAnalyzer
     */
    public function __construct(
        protected QueryTermFrequencyFetcherInterface $queryTermFrequencyFetcher,
        protected QuerySpecificityCalculatorInterface $querySpecificityCalculator,
        protected array $fieldToSearchAnalyzer,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $searchString
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     */
    public function calculateRelevanceWeight(
        string $searchString,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): float {
        return $this->calculateWeightingResult($searchString, $configurationTransfer)->getRelevanceWeight();
    }

    /**
     * {@inheritDoc}
     *
     * @param string $searchString
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     */
    public function calculateWeightingResult(
        string $searchString,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): SpecificityWeightingResult {
        $configuredRelevanceWeight = (float)$configurationTransfer->getRelevanceWeight();
        $specificityWeightExponent = (float)$configurationTransfer->getSpecificityWeightExponent();
        $specificityWeightShiftMagnitude = (float)$configurationTransfer->getSpecificityWeightShiftMagnitude();

        $termFrequencyResult = $this->queryTermFrequencyFetcher->fetch($searchString, $this->fieldToSearchAnalyzer);
        $idfByTerm = $this->calculateIdfByTerm($termFrequencyResult);

        if ($idfByTerm === []) {
            return new SpecificityWeightingResult(
                $configuredRelevanceWeight,
                $configuredRelevanceWeight,
                0.0,
                0.0,
                0,
                $specificityWeightExponent,
                $specificityWeightShiftMagnitude,
            );
        }

        $rawSpecificity = $this->querySpecificityCalculator->calculateRawSpecificity(
            $idfByTerm,
            (float)$configurationTransfer->getSpecificityBlendWeight(),
        );
        $normalizedSpecificity = $this->querySpecificityCalculator->normalize(
            $rawSpecificity,
            (float)$configurationTransfer->getSpecificitySaturationPoint(),
            $configurationTransfer->getSpecificityCurveExponent() ?? 1.0,
        );

        $shift = $this->calculateShift($normalizedSpecificity, $specificityWeightExponent, $specificityWeightShiftMagnitude);
        $relevanceWeight = max(0.0, min(1.0, $configuredRelevanceWeight + $shift));

        return new SpecificityWeightingResult(
            $configuredRelevanceWeight,
            $relevanceWeight,
            $normalizedSpecificity,
            $shift,
            count($idfByTerm),
            $specificityWeightExponent,
            $specificityWeightShiftMagnitude,
        );
    }

    /**
     * `2 * normalizedSpecificity - 1` maps specificity's `[0;1[` range onto a signed `[-1;1]` deviation
     * from the neutral point (normalized specificity exactly 0.5 — i.e. raw specificity exactly at the
     * calibrated saturation point — → deviation 0): positive when the query is MORE specific than typical
     * (shift toward text relevance), negative when it's LESS specific than typical (shift toward business
     * signals). The exponent is applied to the deviation's MAGNITUDE only, sign preserved separately —
     * applying it to `normalizedSpecificity` directly (before centering) would move the neutral point
     * away from 0.5 whenever the exponent isn't exactly 1.0.
     *
     * @param float $normalizedSpecificity
     * @param float $exponent
     * @param float $shiftMagnitude
     */
    protected function calculateShift(float $normalizedSpecificity, float $exponent, float $shiftMagnitude): float
    {
        $signedDeviation = (2 * $normalizedSpecificity) - 1;
        $shapedDeviation = ($signedDeviation <=> 0) * (abs($signedDeviation) ** $exponent);

        return $shiftMagnitude * $shapedDeviation;
    }

    /**
     * A term with zero real matching evidence anywhere in the corpus (`doc_freq` `0`) is skipped entirely
     * rather than treated as maximally specific — it contributes no evidence that the query is actually
     * about something rare in this catalog, so it shouldn't inflate the signal (e.g. a typo or an
     * out-of-catalog word). A missing/zero corpus doc count (probe failed, or found no index) returns an
     * empty map, the same "no usable signal" fallback the caller already handles.
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyResult $termFrequencyResult
     *
     * @return array<string, float>
     */
    protected function calculateIdfByTerm(QueryTermFrequencyResult $termFrequencyResult): array
    {
        $docCount = $termFrequencyResult->getDocCount();

        if ($docCount <= 0) {
            return [];
        }

        $idfByTerm = [];

        foreach ($termFrequencyResult->getTermDocumentFrequencies() as $term => $documentFrequency) {
            if ($documentFrequency <= 0) {
                continue;
            }

            $idfByTerm[$term] = max(0.0, log($docCount / $documentFrequency));
        }

        return $idfByTerm;
    }
}
