<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchRanking;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchRanking\SearchRankingConfig;

/**
 * A regression guard for one specific invariant, not general Config coverage: entropy-aware relevance
 * weighting fires one additional live Elasticsearch query per catalog search once enabled, so its default
 * MUST stay off unless a project explicitly opts in — this test exists to catch an accidental flip of
 * that default before it ships, not to test a plain constant-getter for its own sake.
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchRanking
 * @group SearchRankingConfigTest
 */
class SearchRankingConfigTest extends Unit
{
    /**
     * @return void
     */
    public function testEntropyWeightingIsDisabledByDefault(): void
    {
        $config = new SearchRankingConfig();

        $this->assertFalse(
            $config->isEntropyWeightingEnabled(),
            'Entropy-aware weighting fires an extra live ES query per search — must default OFF, opt-in only.',
        );
    }

    /**
     * @return void
     */
    public function testEntropyProbeResultSizeAndWeightExponentHaveSaneDefaults(): void
    {
        $config = new SearchRankingConfig();

        $this->assertGreaterThan(0, $config->getEntropyProbeResultSize());
        $this->assertSame(1.0, $config->getEntropyWeightExponent());
    }
}
