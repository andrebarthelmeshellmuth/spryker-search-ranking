<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;

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
        return $this->calculateWeightingResult($searchString, $configurationTransfer)->getRelevanceWeightOrFail();
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
    ): SearchRankingSpecificityWeightingResultTransfer {
        $termFrequencyResult = $this->queryTermFrequencyFetcher->fetch($searchString, $this->fieldToSearchAnalyzer);

        return $this->buildWeightingResult($configurationTransfer, $termFrequencyResult);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param string $probeKeyPrefix
     * @param string $searchString
     */
    public function registerProbes(MsearchProbeBatcherInterface $batcher, string $probeKeyPrefix, string $searchString): void
    {
        $this->queryTermFrequencyFetcher->registerProbes($batcher, $probeKeyPrefix, $searchString, $this->fieldToSearchAnalyzer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \SprykerCommunity\Client\SearchRanking\Search\MsearchProbeBatcherInterface $batcher
     * @param string $probeKeyPrefix
     * @param string $searchString
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     */
    public function consumeProbes(
        MsearchProbeBatcherInterface $batcher,
        string $probeKeyPrefix,
        string $searchString,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): SearchRankingSpecificityWeightingResultTransfer {
        $termFrequencyResult = $this->queryTermFrequencyFetcher->consumeProbes(
            $batcher,
            $probeKeyPrefix,
            $searchString,
            $this->fieldToSearchAnalyzer,
        );

        return $this->buildWeightingResult($configurationTransfer, $termFrequencyResult);
    }

    /**
     * Shared downstream computation for {@see calculateWeightingResult()} and {@see consumeProbes()} — the
     * only difference between the two call paths is HOW `$termFrequencyResult` was obtained (a standalone
     * `_msearch` vs. reading back a shared batcher), never how it's interpreted from here on.
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param \SprykerCommunity\Client\SearchRanking\Search\QueryTermFrequencyResult $termFrequencyResult
     */
    protected function buildWeightingResult(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        QueryTermFrequencyResult $termFrequencyResult,
    ): SearchRankingSpecificityWeightingResultTransfer {
        $configuredRelevanceWeight = (float)$configurationTransfer->getRelevanceWeight();
        $specificityWeightExponent = (float)$configurationTransfer->getSpecificityWeightExponent();
        $specificityWeightShiftMagnitude = (float)$configurationTransfer->getSpecificityWeightShiftMagnitude();
        // Real configured values regardless of whether any terms carry evidence below — same reasoning
        // $specificityWeightExponent/$specificityWeightShiftMagnitude already follow into both branches.
        $specificitySaturationPoint = (float)$configurationTransfer->getSpecificitySaturationPoint();
        $specificityCurveExponent = $configurationTransfer->getSpecificityCurveExponent() ?? 1.0;

        $idfByTerm = $this->calculateIdfByTerm($termFrequencyResult);

        if ($idfByTerm === []) {
            return (new SearchRankingSpecificityWeightingResultTransfer())
                ->setConfiguredRelevanceWeight($configuredRelevanceWeight)
                ->setRelevanceWeight($configuredRelevanceWeight)
                ->setNormalizedSpecificity(0.0)
                ->setShift(0.0)
                ->setQueryTermCount(0)
                ->setSpecificityWeightExponent($specificityWeightExponent)
                ->setSpecificityWeightShiftMagnitude($specificityWeightShiftMagnitude)
                ->setRawSpecificity(0.0)
                ->setSpecificitySaturationPoint($specificitySaturationPoint)
                ->setSpecificityCurveExponent($specificityCurveExponent);
        }

        $rawSpecificity = $this->querySpecificityCalculator->calculateRawSpecificity(
            $idfByTerm,
            (float)$configurationTransfer->getSpecificityBlendWeight(),
        );
        $normalizedSpecificity = $this->querySpecificityCalculator->normalize(
            $rawSpecificity,
            $specificitySaturationPoint,
            $specificityCurveExponent,
        );

        $shift = $this->calculateShift($normalizedSpecificity, $specificityWeightExponent, $specificityWeightShiftMagnitude);
        $relevanceWeight = max(0.0, min(1.0, $configuredRelevanceWeight + $shift));

        return (new SearchRankingSpecificityWeightingResultTransfer())
            ->setConfiguredRelevanceWeight($configuredRelevanceWeight)
            ->setRelevanceWeight($relevanceWeight)
            ->setNormalizedSpecificity($normalizedSpecificity)
            ->setShift($shift)
            ->setQueryTermCount(count($idfByTerm))
            ->setSpecificityWeightExponent($specificityWeightExponent)
            ->setSpecificityWeightShiftMagnitude($specificityWeightShiftMagnitude)
            ->setRawSpecificity($rawSpecificity)
            ->setSpecificitySaturationPoint($specificitySaturationPoint)
            ->setSpecificityCurveExponent($specificityCurveExponent);
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
