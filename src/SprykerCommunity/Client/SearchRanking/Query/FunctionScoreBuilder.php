<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Query;

use Elastica\Query\AbstractQuery;
use Elastica\Query\FunctionScore;
use Elastica\Script\Script;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;

/**
 * Builds the business-signal function_score wrapper — a weighted blend of normalized text relevance
 * and the weighted business signals, both scaled to `[0;1]` before combining:
 *
 *   relevanceWeight * (_score / (_score + relevanceSaturationPoint))
 *     + (1 - relevanceWeight) * (w_1 * scores.metric_1 + ... + w_n * scores.metric_n)
 *
 * Elasticsearch's raw `_score` is unbounded and query-shape-dependent (more/rarer matched terms produce
 * much higher scores), while business signals are normalized to `[0;1]` by design — combining them
 * directly (as an older version of this class did, via `(1 + sqrt(_score)) * (signals + baseline)`)
 * meant the RELATIVE influence of business signals over text relevance drifted unpredictably from query
 * to query. That formula wasn't all bad, though: `sqrt(_score)` never saturates, it keeps growing
 * (slowly) as `_score` grows, so on long/specific queries where many rare terms match and `_score` gets
 * large, weight naturally drifted back toward text relevance instead of staying pinned to the business
 * signals — a property this saturating curve deliberately gives up, since `_score`'s contribution here is
 * capped to `[0;1)` no matter how large `_score` gets. Recovering that upside on purpose, rather than as
 * a side effect of an otherwise unpredictable formula, would need real additional machinery (some way to
 * read the distribution of scores across the result set, not just one document at a time) — outside what
 * this package builds, but not incompatible with it. `_score / (_score + relevanceSaturationPoint)` is
 * the same saturating-curve shape BM25 itself uses for term-frequency saturation (also known from
 * Michaelis-Menten kinetics): it
 * maps the unbounded `_score` onto `[0;1)`, reaching exactly 0.5 at `_score == relevanceSaturationPoint`,
 * so both terms of the blend are finally on the same scale. `relevanceWeight` is then one single,
 * interpretable knob for how much of the final score comes from text relevance vs. business signals — see
 * this package's README for the full rationale (and why this replaced an additive "signal baseline"
 * constant). Weights
 * and both blend constants are passed as script params; metric names are embedded in the doc-value
 * paths (guarded for missing fields).
 */
class FunctionScoreBuilder implements FunctionScoreBuilderInterface
{
    /**
     * @var string
     */
    protected const SCORES_FIELD_PREFIX = 'scores.';

    /**
     * Metric names are embedded into the painless source; only names matching this pattern
     * (enforced by the Zed form, but not by data import) are accepted.
     *
     * @var string
     */
    protected const METRIC_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * @param \Elastica\Query\AbstractQuery $wrappedQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return \Elastica\Query\FunctionScore|null
     */
    public function build(
        AbstractQuery $wrappedQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
    ): ?FunctionScore {
        $script = $this->buildScript($configurationTransfer);

        if ($script === null) {
            return null;
        }

        $functionScore = new FunctionScore();
        $functionScore->setQuery($wrappedQuery);
        $functionScore->addScriptScoreFunction($script);
        $functionScore->setBoostMode(FunctionScore::BOOST_MODE_REPLACE);
        $functionScore->setScoreMode(FunctionScore::SCORE_MODE_FIRST);

        return $functionScore;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     *
     * @return \Elastica\Script\Script|null
     */
    protected function buildScript(SearchRankingConfigurationStorageTransfer $configurationTransfer): ?Script
    {
        $signalTerms = [];
        $scriptParams = [];
        $metricIndex = 0;

        foreach ($configurationTransfer->getMetricWeights() as $metricName => $weight) {
            if (!is_string($metricName) || !preg_match(static::METRIC_NAME_PATTERN, $metricName)) {
                continue;
            }

            $weight = (float)$weight;

            if ($weight === 0.0) {
                continue;
            }

            $weightParam = 'w' . $metricIndex;
            $fieldPath = static::SCORES_FIELD_PREFIX . $metricName;

            $signalTerms[] = sprintf(
                "params.%s * ((doc.containsKey('%s') && doc['%s'].size() > 0) ? doc['%s'].value : 0)",
                $weightParam,
                $fieldPath,
                $fieldPath,
                $fieldPath,
            );
            $scriptParams[$weightParam] = $weight;
            $metricIndex++;
        }

        if ($signalTerms === []) {
            return null;
        }

        $scriptParams['relevanceWeight'] = (float)$configurationTransfer->getRelevanceWeight();
        $scriptParams['relevanceSaturationPoint'] = (float)$configurationTransfer->getRelevanceSaturationPoint();

        $source = sprintf(
            'params.relevanceWeight * (_score / (_score + params.relevanceSaturationPoint)) + (1 - params.relevanceWeight) * (%s)',
            implode(' + ', $signalTerms),
        );

        return new Script($source, $scriptParams);
    }
}
