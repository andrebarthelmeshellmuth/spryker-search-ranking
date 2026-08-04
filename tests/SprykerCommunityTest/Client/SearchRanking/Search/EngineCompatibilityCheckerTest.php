<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking\Search;

use Codeception\Test\Unit;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchRanking\Search\EngineCompatibilityChecker;

/**
 * INTEGRATION TEST — talks to a real Elasticsearch/OpenSearch cluster, no test-owned index needed: every
 * probe this class fires is cluster-wide (`_validate/query`, `_all/_rank_eval`), never scoped to an index.
 *
 * This is the first test at all for `EngineCompatibilityChecker` — everything it does (fire real
 * `_validate/query` probes, read back the engine's own parser response, distinguish "recognized but
 * rejected" from "endpoint not recognized at all" for `_rank_eval`) is meaningless against a mocked
 * client: a mock can only ever confirm the PHP calling code shaped a request, never that a real engine
 * actually answers the way the class's own doc comments claim it does. This is also the regression guard
 * for an engine upgrade silently changing that behavior (e.g. an OpenSearch/Elasticsearch version that
 * stops recognizing `_rank_eval` under `_all`, or changes how an unrecognized query clause is rejected).
 *
 * Only asserts on `function_score` + `script_score` — the one capability that actually gates this
 * package's exit code today (see `checkCompatibility()`'s own doc comment: every other probed capability
 * is purely forward-looking). Not asserting on `rank_feature`/`distance_feature`/`pinned`/`_rank_eval`
 * here on purpose: those are roadmap-only capabilities this dev stack happens to support today, and
 * hard-asserting on all of them would make this test fail the moment the roadmap work that actually
 * cares about them changes, or the dev stack's OpenSearch version moves — for a capability nothing here
 * depends on yet.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group Search
 * @group EngineCompatibilityCheckerTest
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

        $functionScoreCapability = null;

        foreach ($compatibilityTransfer->getCapabilities() as $capabilityTransfer) {
            if ($capabilityTransfer->getName() !== static::CAPABILITY_FUNCTION_SCORE) {
                continue;
            }

            $functionScoreCapability = $capabilityTransfer;

            break;
        }

        $this->assertNotNull($functionScoreCapability, 'checkCompatibility() should always probe function_score + script_score.');
        $this->assertTrue(
            $functionScoreCapability->getIsSupported(),
            sprintf(
                'This package requires function_score + script_score support; engine said: %s',
                (string)$functionScoreCapability->getDetail(),
            ),
        );
    }
}
