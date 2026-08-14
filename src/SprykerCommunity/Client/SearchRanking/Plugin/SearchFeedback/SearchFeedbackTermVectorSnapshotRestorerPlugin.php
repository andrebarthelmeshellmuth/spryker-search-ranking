<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Plugin\SearchFeedback;

use Generated\Shared\Transfer\SearchRankingSpecificityWeightingResultTransfer;
use Spryker\Client\Kernel\AbstractPlugin;
use SprykerCommunity\Client\SearchFeedback\Dependency\Plugin\TermVectorSnapshotRestorerPluginInterface;
use Throwable;

/**
 * The restore-side counterpart to {@see SearchFeedbackTermVectorSnapshotProviderPlugin}: during a
 * search-feedback ticket replay, decodes the frozen specificity-weighting result that scored the ticket at
 * filing time and remembers it on this package's own Client via `rememberLastSpecificityWeightingResult()`
 * — the exact same hook `SearchRankingFunctionScoreQueryExpanderPlugin` uses for a LIVE search — so
 * {@see \SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin}'s
 * debug-overlay integration reads the FROZEN value for the remainder of this replay request, not whatever
 * a live query expansion just (redundantly) computed.
 *
 * Confirmed live why this plugin has to exist at all: query expansion still runs fresh on every request,
 * replay or not — `ReplayCapableSearch` only intercepts the actual Elasticsearch call, nothing upstream of
 * it. Without this restore step, a replay's debug overlay silently shows the LIVE current relevanceWeight/
 * specificity numbers instead of the ones that actually scored the ticket — indistinguishable from a
 * correctly-frozen replay until a live setting changes and the same replay is reopened.
 *
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingClientInterface getClient()
 */
class SearchFeedbackTermVectorSnapshotRestorerPlugin extends AbstractPlugin implements TermVectorSnapshotRestorerPluginInterface
{
    /**
     * {@inheritDoc}
     * - The JSON was produced by this same package's own {@see SearchFeedbackTermVectorSnapshotProviderPlugin},
     *   camelCased specifically so `fromArray()`'s own default expectation needs no extra flag here.
     * - Malformed/foreign JSON (e.g. a shop that changed search-ranking's transfer shape after tickets were
     *   already filed) is silently ignored rather than throwing and breaking the whole replay — same
     *   "additive, never a hard requirement" posture the rest of this integration has.
     *
     * @api
     *
     * @param string $termVectorSnapshot
     */
    public function restoreTermVectorSnapshot(string $termVectorSnapshot): void
    {
        $decoded = json_decode($termVectorSnapshot, true);

        if (!is_array($decoded)) {
            return;
        }

        try {
            $specificityWeightingResultTransfer = (new SearchRankingSpecificityWeightingResultTransfer())->fromArray($decoded, true);
        } catch (Throwable) {
            return;
        }

        $this->getClient()->rememberLastSpecificityWeightingResult($specificityWeightingResultTransfer);
    }
}
