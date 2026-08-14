<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Plugin\SearchFeedback;

use Spryker\Client\Kernel\AbstractPlugin;
use SprykerCommunity\Client\SearchFeedback\Dependency\Plugin\TermVectorSnapshotProviderPluginInterface;

/**
 * Optional integration with spryker-community/search-feedback (see composer `suggest`): lets a filed
 * ticket's frozen SRP snapshot also carry the specificity-weighting result that scored it, the same
 * "reads data already computed this request, never a fresh fetch" posture
 * {@see \SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin}
 * already established for search-debug's overlay.
 *
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface getClient()
 */
class SearchFeedbackTermVectorSnapshotProviderPlugin extends AbstractPlugin implements TermVectorSnapshotProviderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Returns null when specificity weighting didn't run for this request's search (disabled via
     *   `isSpecificityWeightingEnabled()`, or the search had no search string) — same "nothing to report"
     *   case {@see \SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin}
     *   already handles identically.
     *
     * @api
     */
    public function getTermVectorSnapshot(): ?string
    {
        $specificityWeightingResult = $this->getClient()->getLastSpecificityWeightingResult();

        if ($specificityWeightingResult === null) {
            return null;
        }

        // camelCased: toArray()'s default is snake_case, which would be the only snake_case JSON payload
        // in an otherwise camelCase transfer (SearchFeedbackTicketSrpSnapshot's own fields).
        return json_encode($specificityWeightingResult->toArray(true, true)) ?: null;
    }
}
