<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use Elastica\Client;
use Elastica\Response;
use Generated\Shared\Transfer\SearchRankingEngineCapabilityTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityChecker;

/**
 * Two kinds of coverage live here:
 *
 * 1. {@see testRealEngineReportsFunctionScoreAndScriptScoreAsSupported} is an INTEGRATION test — it talks
 *    to the real dev-stack cluster, because the one capability that actually gates this package's exit
 *    code (`function_score` + `script_score`) is only meaningfully verified against a real parser.
 *
 * 2. The `testProbes*` cases are query-shape UNIT tests for the forward-looking OpenSearch 3.x probes
 *    (`hybrid`/`neural` query, `_search/pipeline`, `_plugins/_ml`, `_plugins/_ltr`). Those probe
 *    endpoints the dev stack does not necessarily expose, so they are driven by a fake `Elastica\Client`
 *    that returns canned response bodies — the same convention
 *    {@see \SprykerCommunityTest\Client\SearchRanking\Search\MsearchProbeBatcherTest} uses — asserting the
 *    checker maps a recognized vs. an unrecognized engine response onto the right `isSupported` flag.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group EngineCompatibilityCheckerTest
 * @group NeedsSearch
 */
class EngineCompatibilityCheckerTest extends Unit
{
    /**
     * @var string
     *
     * Mirrors the protected `EngineCompatibilityChecker::CAPABILITY_FUNCTION_SCORE` constant — not
     * reachable from outside the class, so the exact label string is duplicated here deliberately.
     */
    protected const CAPABILITY_FUNCTION_SCORE = 'function_score + script_score (painless)';

    /**
     * @var string
     */
    protected const CAPABILITY_HYBRID_QUERY = 'hybrid query';

    /**
     * @var string
     */
    protected const CAPABILITY_NEURAL_QUERY = 'neural query';

    /**
     * @var string
     */
    protected const CAPABILITY_SEARCH_PIPELINE = '_search/pipeline endpoint';

    /**
     * @var string
     */
    protected const CAPABILITY_ML_PLUGIN = '_plugins/_ml endpoint (ML Commons)';

    /**
     * @var string
     */
    protected const CAPABILITY_LTR_PLUGIN = '_plugins/_ltr endpoint (Learning To Rank)';

    public function testRealEngineReportsFunctionScoreAndScriptScoreAsSupported(): void
    {
        // Arrange
        $searchElasticsearchConfig = new SearchElasticsearchConfig();
        $elasticaClient = (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());
        $checker = new EngineCompatibilityChecker($elasticaClient);

        // Act
        $compatibilityTransfer = $checker->checkCompatibility();

        // Assert
        $this->assertNotSame('', $compatibilityTransfer->getDistributionOrFail(), 'A real engine should always self-report something under `version.distribution` (or the "elasticsearch" fallback).');
        $this->assertNotSame('unknown', $compatibilityTransfer->getVersionOrFail(), 'A reachable real engine should always self-report a version number.');

        $functionScoreCapability = $this->findCapability($compatibilityTransfer, static::CAPABILITY_FUNCTION_SCORE);

        $this->assertNotNull($functionScoreCapability, 'checkCompatibility() should always probe function_score + script_score.');
        $this->assertTrue(
            $functionScoreCapability->getIsSupported(),
            sprintf(
                'This package requires function_score + script_score support; engine said: %s',
                (string)$functionScoreCapability->getDetail(),
            ),
        );
    }

    /**
     * @dataProvider validateQueryProbeCaseProvider
     *
     * @param string $capabilityName
     * @param string $queryClauseNeedle
     * @param array<string, mixed> $cannedValidateQueryResponse
     * @param bool $expectedSupported
     */
    public function testProbesQueryDslCapabilityFromValidateQueryResponse(
        string $capabilityName,
        string $queryClauseNeedle,
        array $cannedValidateQueryResponse,
        bool $expectedSupported,
    ): void {
        // Arrange
        $fakeClient = $this->createFakeClient([
            [$queryClauseNeedle, $cannedValidateQueryResponse],
        ]);
        $checker = new EngineCompatibilityChecker($fakeClient);

        // Act
        $capabilityTransfer = $this->findCapability($checker->checkCompatibility(), $capabilityName);

        // Assert
        $this->assertNotNull($capabilityTransfer, sprintf('checkCompatibility() should always probe "%s".', $capabilityName));
        $this->assertSame($expectedSupported, $capabilityTransfer->getIsSupported(), (string)$capabilityTransfer->getDetail());
    }

    /**
     * @dataProvider endpointRecognitionProbeCaseProvider
     *
     * @param string $capabilityName
     * @param string $pathNeedle
     * @param array<string, mixed> $cannedEndpointResponse
     * @param bool $expectedSupported
     */
    public function testProbesEndpointRecognitionFromBarePathResponse(
        string $capabilityName,
        string $pathNeedle,
        array $cannedEndpointResponse,
        bool $expectedSupported,
    ): void {
        // Arrange
        $fakeClient = $this->createFakeClient([
            [$pathNeedle, $cannedEndpointResponse],
        ]);
        $checker = new EngineCompatibilityChecker($fakeClient);

        // Act
        $capabilityTransfer = $this->findCapability($checker->checkCompatibility(), $capabilityName);

        // Assert
        $this->assertNotNull($capabilityTransfer, sprintf('checkCompatibility() should always probe "%s".', $capabilityName));
        $this->assertSame($expectedSupported, $capabilityTransfer->getIsSupported(), (string)$capabilityTransfer->getDetail());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: bool}>
     */
    public function validateQueryProbeCaseProvider(): array
    {
        return [
            'hybrid query recognized by the parser' => [
                static::CAPABILITY_HYBRID_QUERY,
                '"hybrid"',
                ['valid' => true, '_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0]],
                true,
            ],
            'hybrid query unknown to the parser' => [
                static::CAPABILITY_HYBRID_QUERY,
                '"hybrid"',
                ['valid' => false, 'error' => 'no [query] registered for [hybrid]'],
                false,
            ],
            'neural query recognized by the parser' => [
                static::CAPABILITY_NEURAL_QUERY,
                '"neural"',
                ['valid' => true, '_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0]],
                true,
            ],
            'neural query unknown to the parser' => [
                static::CAPABILITY_NEURAL_QUERY,
                '"neural"',
                ['valid' => false, 'error' => 'no [query] registered for [neural]'],
                false,
            ],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: bool}>
     */
    public function endpointRecognitionProbeCaseProvider(): array
    {
        $noHandlerStructuredError = [
            'error' => [
                'root_cause' => [
                    ['type' => 'no_handler_found_for_uri_exception', 'reason' => 'no handler found for uri [/x] and method [GET]'],
                ],
                'type' => 'no_handler_found_for_uri_exception',
                'reason' => 'no handler found for uri [/x] and method [GET]',
            ],
            'status' => 400,
        ];

        return [
            '_search/pipeline recognized (returns an empty pipeline map)' => [
                static::CAPABILITY_SEARCH_PIPELINE,
                '_search/pipeline',
                [],
                true,
            ],
            '_search/pipeline not recognized (structured no-handler error)' => [
                static::CAPABILITY_SEARCH_PIPELINE,
                '_search/pipeline',
                $noHandlerStructuredError,
                false,
            ],
            '_plugins/_ml recognized (returns a node-stats map)' => [
                static::CAPABILITY_ML_PLUGIN,
                '_plugins/_ml',
                ['cluster_name' => 'docker-cluster', 'nodes' => ['count' => 1]],
                true,
            ],
            '_plugins/_ml not recognized (structured no-handler error)' => [
                static::CAPABILITY_ML_PLUGIN,
                '_plugins/_ml',
                $noHandlerStructuredError,
                false,
            ],
            '_plugins/_ltr recognized (returns a store map)' => [
                static::CAPABILITY_LTR_PLUGIN,
                '_plugins/_ltr',
                ['stores' => ['_default_' => ['status' => 'green']]],
                true,
            ],
            '_plugins/_ltr not recognized (bare-string no-handler error)' => [
                static::CAPABILITY_LTR_PLUGIN,
                '_plugins/_ltr',
                ['error' => 'no handler found for uri [/_plugins/_ltr] and method [GET]', 'status' => 400],
                false,
            ],
        ];
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer $compatibilityTransfer
     * @param string $capabilityName
     */
    protected function findCapability(
        SearchRankingEngineCompatibilityTransfer $compatibilityTransfer,
        string $capabilityName,
    ): ?SearchRankingEngineCapabilityTransfer {
        foreach ($compatibilityTransfer->getCapabilities() as $capabilityTransfer) {
            if ($capabilityTransfer->getName() === $capabilityName) {
                return $capabilityTransfer;
            }
        }

        return null;
    }

    /**
     * Fake `Elastica\Client`: for each request it returns the canned body of the FIRST `[needle, body]`
     * pair whose needle occurs in the request path or its JSON-encoded body; unmatched requests (the root
     * identification call, `_rank_eval`, the completion-suggester probe index, …) get a neutral empty
     * body so only the probe under test is exercised.
     *
     * @param array<int, array{0: string, 1: array<string, mixed>}> $cannedResponsesByNeedle
     */
    protected function createFakeClient(array $cannedResponsesByNeedle): Client
    {
        return new class ($cannedResponsesByNeedle) extends Client {
            /**
             * @param array<int, array{0: string, 1: array<string, mixed>}> $cannedResponsesByNeedle
             */
            public function __construct(protected array $cannedResponsesByNeedle)
            {
            }

            /**
             * @param mixed $data
             * @param array<string, mixed> $query
             */
            public function request(string $path, string $method = 'GET', $data = [], array $query = [], string $contentType = 'application/json'): Response
            {
                unset($method, $query, $contentType);

                $haystack = $path . ' ' . json_encode($data);

                foreach ($this->cannedResponsesByNeedle as [$needle, $body]) {
                    if (str_contains($haystack, $needle)) {
                        return new Response($body, 200);
                    }
                }

                return new Response([], 200);
            }
        };
    }
}
