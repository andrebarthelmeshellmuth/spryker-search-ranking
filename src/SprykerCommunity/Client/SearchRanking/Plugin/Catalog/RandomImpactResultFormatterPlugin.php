<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Plugin\Catalog;

use Elastica\Result;
use Elastica\ResultSet;
use Generated\Shared\Search\PageIndexMap;
use Spryker\Client\SearchElasticsearch\Plugin\ResultFormatter\AbstractElasticsearchResultFormatterPlugin;
use SprykerCommunity\Shared\SearchRanking\Plugin\SeeSearchRankingRandomImpactPermissionPlugin;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

/**
 * Computes, for every hit on the CURRENT page only (not a full-corpus re-query — see this package's
 * README, "Random impact" for the rationale and the same-page-only caveat), how far each product would
 * move if the configured random tie-breaker metric's weight were 0 instead of its real live value.
 * Admin-only ({@see SeeSearchRankingRandomImpactPermissionPlugin}) and computed unconditionally at
 * page-load time whenever a human with that permission is browsing — the search results Twig view then
 * decides whether to actually SHOW the badges, via its own "Show random impact" checkbox; this plugin's
 * own payload carries every delta regardless, cheap to compute compared to a real search query.
 *
 * @method \SprykerCommunity\Client\SearchRanking\SearchRankingFactory getFactory()
 */
class RandomImpactResultFormatterPlugin extends AbstractElasticsearchResultFormatterPlugin
{
    /**
     * @var string
     */
    public const NAME = SharedSearchRankingConfig::RANDOM_IMPACT_RESULT_KEY;

    /**
     * @var string
     */
    protected const PRODUCT_ID_KEY = 'id_product_abstract';

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * {@inheritDoc}
     * - Returns `[]` (both keys absent) for a customer without the permission, or when no ranking
     *   configuration is synchronized for this (store, locale) at all -- the Twig view treats either the
     *   same as `isActive: false`.
     *
     * @param \Elastica\ResultSet $searchResult
     * @param array<string, mixed> $requestParameters
     *
     * @return array<string, mixed>
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- signature is fixed by AbstractElasticsearchResultFormatterPlugin.

    protected function formatSearchResult(ResultSet $searchResult, array $requestParameters): array
    {
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
        if (!$this->getFactory()->getPermissionClient()->can(SeeSearchRankingRandomImpactPermissionPlugin::KEY)) {
            return [];
        }

        $configurationTransfer = $this->getFactory()->getSearchRankingStorageClient()->findRankingConfiguration(
            $this->getFactory()->getStoreClient()->getCurrentStore()->getNameOrFail(),
            $this->getFactory()->getLocaleClient()->getCurrentLocale(),
        );

        if ($configurationTransfer === null) {
            return [];
        }

        $randomImpactCalculator = $this->getFactory()->createRandomImpactCalculator();

        if (!$randomImpactCalculator->isActive($configurationTransfer)) {
            return [
                SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE => false,
                SharedSearchRankingConfig::RANDOM_IMPACT_KEY_DELTAS => [],
            ];
        }

        $hits = $this->extractHits($searchResult, $configurationTransfer->getRandomMetricNameOrFail());

        return [
            SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE => true,
            SharedSearchRankingConfig::RANDOM_IMPACT_KEY_DELTAS => $randomImpactCalculator->calculate($hits, $configurationTransfer),
        ];
    }

    /**
     * @param \Elastica\ResultSet $searchResult
     * @param string $randomMetricName
     *
     * @return array<int, array{idProductAbstract: int, score: float, randomSignal: float}>
     */
    protected function extractHits(ResultSet $searchResult, string $randomMetricName): array
    {
        $hits = [];

        foreach ($searchResult->getResults() as $document) {
            $source = $document->getSource();

            if (!isset($source[PageIndexMap::SEARCH_RESULT_DATA][static::PRODUCT_ID_KEY])) {
                continue;
            }

            $hits[] = [
                'idProductAbstract' => (int)$source[PageIndexMap::SEARCH_RESULT_DATA][static::PRODUCT_ID_KEY],
                'score' => $this->getScore($document),
                'randomSignal' => (float)($source[SharedSearchRankingConfig::PAGE_INDEX_FIELD_SCORES][$randomMetricName] ?? 0.0),
            ];
        }

        return $hits;
    }

    /**
     * `Elastica\Result::getScore()` returns an empty ARRAY, not `0.0`, for a hit Elasticsearch sent with
     * `"_score": null` (a non-relevance-sorted search) -- same defensive read search-debug's own
     * `SearchDebugResultFormatterPlugin` uses, so an unscored hit contributes 0 rather than a type error.
     *
     * @param \Elastica\Result $document
     */
    protected function getScore(Result $document): float
    {
        if (!$document->hasParam('_score')) {
            return 0.0;
        }

        return (float)$document->getScore();
    }
}
