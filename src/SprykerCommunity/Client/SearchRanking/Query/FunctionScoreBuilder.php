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
 * Builds the business-signal function_score wrapper:
 *
 *   (1 + sqrt(_score)) * (w_1 * scores.metric_1 + ... + w_n * scores.metric_n + floor)
 *
 * The sqrt dampens text-relevance differences so business signals can compete; the additive floor
 * keeps products without signals from being multiplied towards zero. Weights and floor are passed
 * as script params, metric names are embedded in the doc-value paths (guarded for missing fields).
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

        $scriptParams['floor'] = (float)$configurationTransfer->getScoreFloor();

        $source = sprintf(
            '(1 + Math.sqrt(_score)) * (%s + params.floor)',
            implode(' + ', $signalTerms),
        );

        return new Script($source, $scriptParams);
    }
}
