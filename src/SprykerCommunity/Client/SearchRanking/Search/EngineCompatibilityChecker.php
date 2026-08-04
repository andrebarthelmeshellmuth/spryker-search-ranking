<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Elastica\Client;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Generated\Shared\Transfer\SearchRankingEngineCapabilityTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;

/**
 * Probes the live search engine's ACTUAL capabilities via `_validate/query` (query-DSL constructs) and a
 * minimal `_rank_eval` request (endpoint recognition) — never by comparing a version string, since
 * OpenSearch and Elasticsearch report incompatible version identifiers under the same API surface
 * (confirmed live: this stack self-reports `distribution: opensearch, 1.3.4`; a bare Elasticsearch
 * cluster reports a bare version number with no `distribution` field at all). Every probe runs
 * cluster-wide (no index in the request path): capability support is a property of the engine, not of
 * any one store's index.
 */
class EngineCompatibilityChecker implements EngineCompatibilityCheckerInterface
{
    /**
     * @var string
     */
    protected const CAPABILITY_FUNCTION_SCORE = 'function_score + script_score (painless)';

    /**
     * @var string
     */
    protected const CAPABILITY_RANK_FEATURE = 'rank_feature query';

    /**
     * @var string
     */
    protected const CAPABILITY_DISTANCE_FEATURE = 'distance_feature query';

    /**
     * @var string
     */
    protected const CAPABILITY_PINNED = 'pinned query';

    /**
     * @var string
     */
    protected const CAPABILITY_RANK_EVAL = '_rank_eval endpoint';

    /**
     * Root-cause exception types seen when `_all/_rank_eval` is NOT a recognized endpoint at all: the
     * request falls through to an unrelated, decades-old routing fallback (the legacy `{index}/{type}`
     * mapping API) and fails on something that has nothing to do with rank_eval — confirmed live by
     * deliberately requesting a nonexistent endpoint the same way and comparing the error shape.
     *
     * @var array<string>
     */
    protected const RANK_EVAL_UNRECOGNIZED_MARKERS = [
        'invalid_type_name_exception',
        'invalid_index_name_exception',
        'no_handler_found_for_uri_exception',
    ];

    /**
     * @param \Elastica\Client $elasticaClient
     */
    public function __construct(protected Client $elasticaClient)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function checkCompatibility(): SearchRankingEngineCompatibilityTransfer
    {
        $compatibilityTransfer = $this->buildEngineIdentification();

        $compatibilityTransfer->addCapability($this->probeQuery(
            static::CAPABILITY_FUNCTION_SCORE,
            [
                'query' => [
                    'function_score' => [
                        'query' => ['match_all' => (object)[]],
                        'script_score' => [
                            'script' => ['lang' => 'painless', 'source' => '1 + Math.sqrt(_score)'],
                        ],
                    ],
                ],
            ],
        ));

        $compatibilityTransfer->addCapability($this->probeQuery(
            static::CAPABILITY_RANK_FEATURE,
            ['query' => ['rank_feature' => ['field' => 'search_ranking_compatibility_probe_field']]],
        ));

        $compatibilityTransfer->addCapability($this->probeQuery(
            static::CAPABILITY_DISTANCE_FEATURE,
            [
                'query' => [
                    'distance_feature' => [
                        'field' => 'search_ranking_compatibility_probe_field',
                        'pivot' => '7d',
                        'origin' => 'now',
                    ],
                ],
            ],
        ));

        $compatibilityTransfer->addCapability($this->probeQuery(
            static::CAPABILITY_PINNED,
            ['query' => ['pinned' => ['ids' => ['search_ranking_compatibility_probe_id'], 'organic' => ['match_all' => (object)[]]]]],
        ));

        $compatibilityTransfer->addCapability($this->probeRankEval());

        return $compatibilityTransfer;
    }

    protected function buildEngineIdentification(): SearchRankingEngineCompatibilityTransfer
    {
        $rootResponseData = $this->requestData('', Request::GET, []);
        $versionData = is_array($rootResponseData['version'] ?? null) ? $rootResponseData['version'] : [];

        $compatibilityTransfer = new SearchRankingEngineCompatibilityTransfer();
        $compatibilityTransfer->setDistribution((string)($versionData['distribution'] ?? 'elasticsearch'));
        $compatibilityTransfer->setVersion((string)($versionData['number'] ?? 'unknown'));

        return $compatibilityTransfer;
    }

    /**
     * Fires $queryBody at the cluster-wide `_validate/query` endpoint. A query-DSL construct the engine
     * does not recognize at all (e.g. `pinned` on OpenSearch) comes back `valid: false` with a top-level
     * parser `error` message; one it DOES recognize comes back `valid: true` regardless of whether the
     * referenced field is actually mapped on any live index (an unmapped field just makes the query match
     * nothing — that is a data question, not a capability question).
     *
     * @param string $capabilityName
     * @param array<string, mixed> $queryBody
     */
    protected function probeQuery(string $capabilityName, array $queryBody): SearchRankingEngineCapabilityTransfer
    {
        $responseData = $this->requestData('_validate/query', Request::GET, $queryBody, ['explain' => 'true']);

        $isSupported = (bool)($responseData['valid'] ?? false);
        $detail = $isSupported
            ? 'Query type is recognized and parses successfully.'
            : (string)($responseData['error'] ?? 'Rejected by the query parser (no further detail returned).');

        return $this->buildCapabilityTransfer($capabilityName, $isSupported, $detail);
    }

    /**
     * `_rank_eval` is a distinct API, not a query clause, so it cannot be probed via `_validate/query`.
     * Instead fires a minimal, deliberately incomplete request (no rated search requests) and inspects
     * the failure shape: a recognized endpoint fails on ITS OWN validation logic (an
     * `x_content_parse_exception`/`illegal_argument_exception` about the empty `requests` array); an
     * engine that does not know this endpoint falls through to the legacy `{index}/{type}` mapping-API
     * route instead and fails on something unrelated (see {@see RANK_EVAL_UNRECOGNIZED_MARKERS}).
     */
    protected function probeRankEval(): SearchRankingEngineCapabilityTransfer
    {
        $responseData = $this->requestData('_all/_rank_eval', Request::POST, [
            'requests' => [],
            'metric' => ['precision' => ['k' => 10]],
        ]);

        $errorData = is_array($responseData['error'] ?? null) ? $responseData['error'] : [];
        $rootCauseType = (string)($errorData['root_cause'][0]['type'] ?? '');

        $isSupported = $rootCauseType !== '' && !in_array($rootCauseType, static::RANK_EVAL_UNRECOGNIZED_MARKERS, true);
        $detail = $isSupported
            ? 'Endpoint is recognized (rejected only the deliberately empty request body, as expected).'
            : (string)($errorData['reason'] ?? $responseData['error'] ?? 'Endpoint was not recognized.');

        return $this->buildCapabilityTransfer(static::CAPABILITY_RANK_EVAL, $isSupported, $detail);
    }

    /**
     * Elastica throws {@see ResponseException} whenever the DECODED RESPONSE BODY carries a top-level
     * `error` key — regardless of HTTP status — which is exactly the shape every "not supported" probe
     * result in this class produces by design (confirmed live: the `pinned`-query probe threw rather than
     * returning `valid: false`). The response body is still fully readable off the exception itself, so
     * every probe treats "engine says no" and "engine says yes" identically: read the body either way.
     *
     * @param string $path
     * @param string $method
     * @param array<string, mixed> $data
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    protected function requestData(string $path, string $method, array $data, array $query = []): array
    {
        try {
            return $this->elasticaClient->request($path, $method, $data, $query)->getData();
        } catch (ResponseException $exception) {
            return $exception->getResponse()->getData();
        }
    }

    /**
     * @param string $name
     * @param bool $isSupported
     * @param string $detail
     */
    protected function buildCapabilityTransfer(string $name, bool $isSupported, string $detail): SearchRankingEngineCapabilityTransfer
    {
        $capabilityTransfer = new SearchRankingEngineCapabilityTransfer();
        $capabilityTransfer->setName($name);
        $capabilityTransfer->setIsSupported($isSupported);
        $capabilityTransfer->setDetail($detail);

        return $capabilityTransfer;
    }
}
