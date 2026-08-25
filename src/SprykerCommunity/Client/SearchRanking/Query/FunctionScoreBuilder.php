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
 * directly would mean the RELATIVE influence of business signals over text relevance drifts
 * unpredictably from query to query. `_score / (_score + relevanceSaturationPoint)` is the same
 * saturating-curve shape BM25 itself uses for term-frequency saturation (also known from
 * Michaelis-Menten kinetics): it maps the unbounded `_score` onto `[0;1)`, reaching exactly 0.5 at
 * `_score == relevanceSaturationPoint`, so both terms of the blend are finally on the same scale.
 * `relevanceWeight` is then one single, interpretable knob for how much of the final score comes from
 * text relevance vs. business signals — see this package's README for the full rationale. Weights and
 * both blend constants are passed as script params; metric names are embedded in the doc-value paths
 * (guarded for missing fields).
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
     * The `page.json` field the semantic (kNN) product vector is stored in — validated against
     * {@see METRIC_NAME_PATTERN} at runtime (defense-in-depth: it's a fixed literal here, not user input,
     * but the metric field paths right above it get the same treatment, so this stays consistent with
     * them rather than being a silent exception).
     *
     * @var string
     */
    protected const EMBEDDING_FIELD = 'embedding';

    /**
     * @param \Elastica\Query\AbstractQuery $wrappedQuery
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<int, float>|null $queryVector
     */
    public function build(
        AbstractQuery $wrappedQuery,
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        ?array $queryVector = null,
    ): ?FunctionScore {
        $script = $this->buildScript($configurationTransfer, $queryVector);

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
     * @param array<int, float>|null $queryVector
     */
    protected function buildScript(SearchRankingConfigurationStorageTransfer $configurationTransfer, ?array $queryVector = null): ?Script
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

        $textComponent = $this->buildTextComponent($configurationTransfer, $queryVector, $scriptParams);

        $source = sprintf(
            'params.relevanceWeight * (%s) + (1 - params.relevanceWeight) * (%s)',
            $textComponent,
            implode(' + ', $signalTerms),
        );

        return new Script($source, $scriptParams);
    }

    /**
     * Builds the text-relevance component of the blend. When a query vector was resolved AND `alpha` is
     * below `1.0`, extends the plain saturated `_score` term with a semantic (kNN cosine similarity) term
     * — guarded per-document, since not every product necessarily has a stored embedding:
     *
     *   (doc has 'embedding')
     *     ? alpha * (_score / (_score + relevanceSaturationPoint))
     *       + (1 - alpha) * ((cosineSimilarity(queryVector, doc['embedding']) + 1) / 2)
     *     : (_score / (_score + relevanceSaturationPoint))
     *
     * When no query vector is available (embedding failure/empty search string at query time) or
     * `alpha == 1.0` (the default — 100% lexical), returns exactly the original, unextended term with NO
     * added script complexity — this is the mandatory degrade-to-today's-formula path.
     *
     * @param \Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer $configurationTransfer
     * @param array<int, float>|null $queryVector
     * @param array<string, mixed> $scriptParams
     */
    protected function buildTextComponent(
        SearchRankingConfigurationStorageTransfer $configurationTransfer,
        ?array $queryVector,
        array &$scriptParams,
    ): string {
        $saturatedScoreTerm = '_score / (_score + params.relevanceSaturationPoint)';

        $alpha = $configurationTransfer->getAlpha();

        // A null alpha (transfer built without an explicit setAlpha() call — never happens on the real
        // KV-read path, which always fills a default, but can happen on a hand-built transfer) is treated
        // the same as the documented default of 1.0: 100% lexical, no semantic term.
        if ($queryVector === null || $queryVector === [] || $alpha === null || $alpha >= 1.0 || !preg_match(static::METRIC_NAME_PATTERN, static::EMBEDDING_FIELD)) {
            return $saturatedScoreTerm;
        }

        $scriptParams['alpha'] = $alpha;
        $scriptParams['queryVector'] = array_values($queryVector);

        $semanticTerm = sprintf(
            "(cosineSimilarity(params.queryVector, doc['%s']) + 1) / 2",
            static::EMBEDDING_FIELD,
        );

        return sprintf(
            "(doc.containsKey('%s') && doc['%s'].size() > 0) ? (params.alpha * (%s) + (1 - params.alpha) * (%s)) : (%s)",
            static::EMBEDDING_FIELD,
            static::EMBEDDING_FIELD,
            $saturatedScoreTerm,
            $semanticTerm,
            $saturatedScoreTerm,
        );
    }
}
