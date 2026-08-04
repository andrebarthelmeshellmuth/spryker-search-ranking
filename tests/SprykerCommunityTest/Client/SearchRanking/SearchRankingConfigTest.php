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
 * A regression guard for one specific invariant, not general Config coverage: specificity-aware relevance
 * weighting fires one additional live `_termvectors` probe per catalog search once enabled, so its default
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
    public function testSpecificityWeightingIsDisabledByDefault(): void
    {
        $config = new SearchRankingConfig();

        $this->assertFalse(
            $config->isSpecificityWeightingEnabled(),
            'Specificity-aware weighting fires an extra live probe per search — must default OFF, opt-in only.',
        );
    }

    public function testDefaultProbeFieldSearchAnalyzersCoverBothStandardFulltextFields(): void
    {
        $config = new SearchRankingConfig();

        $this->assertSame(
            [
                'full-text' => 'standard',
                'full-text-boosted' => 'standard',
            ],
            $config->getSpecificityProbeFieldSearchAnalyzers(),
            'Safe on a vanilla install (standard always exists); a project with its own custom search-time analyzer must override this.',
        );
    }
}
