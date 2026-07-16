<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Debug;

use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

/**
 * Builds the business-signal breakdown section for the search-debug SRP overlay: one line per
 * weighted metric (normalized signal × weight = contribution), the score floor, their sum, and —
 * when the wrapped query's own relevance score is known — the full combination formula, so the
 * final `_score` is reproducible by eye:
 *
 *   top_seller: 0.5099 × 0.50 = 0.2550
 *   pdp_impressions: 0.2033 × 0.30 = 0.0610
 *   score floor: 0.5000
 *   Business signal total: 0.8160
 *   (1 + √6.9244) × 0.8160 = 2.9634
 */
class ScoreSectionBuilder implements ScoreSectionBuilderInterface
{
    /**
     * @var string
     */
    protected const KEY_SCORE_FLOOR_LABEL = 'score floor';

    /**
     * @var string
     */
    protected const SECTION_TITLE = 'Business signals';

    /**
     * @var string
     */
    protected const SUMMARY_LABEL = 'Business signal total';

    /**
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<string, float> $documentScores
     * @param float|null $queryScore
     *
     * @return array<string, mixed>|null
     */
    public function build(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        array $documentScores,
        ?float $queryScore,
    ): ?array {
        $lines = [];
        $signalTotal = 0.0;

        foreach ($configurationTransfer->getMetricWeights() as $metricName => $weight) {
            $weight = (float)$weight;
            $signal = (float)($documentScores[$metricName] ?? 0.0);
            $contribution = $signal * $weight;
            $signalTotal += $contribution;

            $lines[] = [
                'label' => (string)$metricName,
                'calculation' => sprintf('%.4f × %.2f', $signal, $weight),
                'value' => $contribution,
            ];
        }

        if ($lines === []) {
            return null;
        }

        $scoreFloor = (float)$configurationTransfer->getScoreFloor();
        $signalTotal += $scoreFloor;

        $lines[] = [
            'label' => static::KEY_SCORE_FLOOR_LABEL,
            'calculation' => '',
            'value' => $scoreFloor,
        ];

        $section = [
            'title' => static::SECTION_TITLE,
            'lines' => $lines,
            'summaryLabel' => static::SUMMARY_LABEL,
            'summaryValue' => $signalTotal,
        ];

        if ($queryScore !== null && $queryScore >= 0) {
            $section['formula'] = sprintf(
                '(1 + √%.4f) × %.4f = %.4f',
                $queryScore,
                $signalTotal,
                (1 + sqrt($queryScore)) * $signalTotal,
            );
        }

        return $section;
    }
}
