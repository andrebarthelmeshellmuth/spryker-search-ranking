<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug;

use Generated\Shared\Search\PageIndexMap;
use Spryker\Client\Kernel\AbstractPlugin;
use SprykerCommunity\Client\SearchDebug\Dependency\Plugin\ProductDebugDataExpanderPluginInterface;
use SprykerCommunity\Client\SearchDebug\Explanation\ExplanationParser;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;

/**
 * Optional integration with spryker-community/search-debug (see composer `suggest`): explains in the
 * SRP debug overlay how the business signals produced the final score — one line per metric
 * (signal × weight = contribution), their total, the relevance saturation/weight, and the combination
 * formula.
 *
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface getClient()
 */
class SearchRankingProductDebugDataExpanderPlugin extends AbstractPlugin implements ProductDebugDataExpanderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Adds the business-signal score section based on the document's `scores` field and the ranking
     *   configuration (metric weights + relevanceWeight + relevanceSaturationPoint) from key-value
     *   storage.
     * - When specificity weighting ran for this query (see
     *   {@see \SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin}),
     *   reads its result off this package's own Client — NOT a fresh, independent config lookup — so the
     *   relevanceWeight shown/used here is the SAME one that actually scored this hit, and adds a second
     *   "Specificity weighting" section showing the configured baseline, the measured specificity, the
     *   shift, and the resulting effective weight.
     * - Leaves the debug data untouched when no ranking configuration is synchronized or it holds no
     *   metric weights, and specificity weighting didn't run either.
     *
     * @api
     *
     * @param array<string, mixed> $productDebugData
     * @param array<string, mixed> $documentSource
     *
     * @return array<string, mixed>
     */
    public function expandProductDebugData(array $productDebugData, array $documentSource): array
    {
        $configurationTransfer = $this->getFactory()->getSearchRankingStorageClient()->findRankingConfiguration(
            $this->getFactory()->getStoreClient()->getCurrentStore()->getNameOrFail(),
            $this->getFactory()->getLocaleClient()->getCurrentLocale(),
        );

        if ($configurationTransfer === null) {
            return $productDebugData;
        }

        $specificityWeightingResult = $this->getClient()->getLastSpecificityWeightingResult();

        $section = $this->getFactory()->createScoreSectionBuilder()->build(
            $configurationTransfer,
            $documentSource[PageIndexMap::SCORES] ?? [],
            $productDebugData[ExplanationParser::KEY_QUERY_SCORE] ?? null,
            $specificityWeightingResult,
        );

        if ($section !== null) {
            $productDebugData[SearchDebugConfig::KEY_SCORE_SECTIONS][] = $section;
        }

        if ($specificityWeightingResult !== null) {
            $productDebugData[SearchDebugConfig::KEY_SCORE_SECTIONS][] = $this->getFactory()
                ->createScoreSectionBuilder()
                ->buildSpecificitySection($specificityWeightingResult);
        }

        return $productDebugData;
    }
}
