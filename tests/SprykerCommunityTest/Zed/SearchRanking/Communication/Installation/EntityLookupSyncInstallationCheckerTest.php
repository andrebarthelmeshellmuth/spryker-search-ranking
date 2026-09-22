<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Communication\Installation;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchRanking\Communication\Installation\EntityLookupSyncInstallationChecker;
use SprykerCommunity\Zed\SearchRanking\SearchRankingConfig;

/**
 * PORTABLE unit coverage for {@see EntityLookupSyncInstallationChecker}'s raw signal computation, run
 * against this package's own standalone vendor tree (real `spryker/product-page-search` is a hard
 * requirement so present; `spryker/symfony-scheduler` is only a `suggest` so absent here — matching a
 * real standalone install exactly). Deliberately does NOT cover the "event-hook genuinely wired" or
 * "cron genuinely scheduled via a resolved SymfonySchedulerConfig" paths, since reproducing those needs a
 * real project's `Pyz\Zed\...` overrides this package cannot fabricate standalone — see
 * {@see \SprykerCommunityTest\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsoleEntityLookupSyncTest}
 * for the decision/formatting logic this checker's result feeds, tested independently via a stub.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Communication
 * @group Installation
 * @group EntityLookupSyncInstallationCheckerTest
 * @group Portable
 */
class EntityLookupSyncInstallationCheckerTest extends Unit
{
    /**
     * No project override present in this standalone vendor tree, spryker/symfony-scheduler absent, and
     * core's own ProductPageSearchDependencyProvider registers none of this package's plugins — the exact
     * shape of a fresh, not-yet-configured installation.
     */
    public function testReportsNeitherMechanismConfiguredOnAFreshStandaloneInstall(): void
    {
        // Arrange
        $checker = new EntityLookupSyncInstallationChecker(new SearchRankingConfig());

        // Act
        $diagnosisTransfer = $checker->check();

        // Assert
        $this->assertFalse($diagnosisTransfer->getCronConfiguredOrFail());
        $this->assertFalse($diagnosisTransfer->getEventHookRegisteredOrFail());
        $this->assertFalse($diagnosisTransfer->getEventHookRegistrationUnknownOrFail(), 'spryker/product-page-search is a hard requirement, so this should always be resolvable here.');
    }

    /**
     * The config's self-declared flag is honored even with no resolvable scheduler config to cross-check
     * it against (spryker/symfony-scheduler is absent) — the documented fallback path.
     */
    public function testHonorsTheConfigsSelfDeclaredCronFlagWhenNoSchedulerConfigIsResolvable(): void
    {
        // Arrange
        $config = new class extends SearchRankingConfig {
            public function isEntityLookupCronConfigured(): bool
            {
                return true;
            }
        };
        $checker = new EntityLookupSyncInstallationChecker($config);

        // Act
        $diagnosisTransfer = $checker->check();

        // Assert
        $this->assertTrue($diagnosisTransfer->getCronConfiguredOrFail());
    }
}
