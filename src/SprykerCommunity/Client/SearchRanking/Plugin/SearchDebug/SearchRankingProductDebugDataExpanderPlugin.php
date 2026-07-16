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
 * (signal × weight = contribution), the floor, their total, and the combination formula.
 *
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 */
class SearchRankingProductDebugDataExpanderPlugin extends AbstractPlugin implements ProductDebugDataExpanderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Adds the business-signal score section based on the document's `scores` field and the ranking
     *   configuration (weights + floor) from key-value storage.
     * - Leaves the debug data untouched when no ranking configuration is synchronized or it holds no
     *   metric weights.
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
        $configurationTransfer = $this->getFactory()->getSearchRankingStorageClient()->findRankingConfiguration();

        if ($configurationTransfer === null) {
            return $productDebugData;
        }

        $section = $this->getFactory()->createScoreSectionBuilder()->build(
            $configurationTransfer,
            $documentSource[PageIndexMap::SCORES] ?? [],
            $productDebugData[ExplanationParser::KEY_QUERY_SCORE] ?? null,
        );

        if ($section === null) {
            return $productDebugData;
        }

        $productDebugData[SearchDebugConfig::KEY_SCORE_SECTIONS][] = $section;

        return $productDebugData;
    }
}
