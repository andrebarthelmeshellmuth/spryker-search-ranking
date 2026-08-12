<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingStorage\Communication\Plugin\Synchronization;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SynchronizationDataTransfer;
use Orm\Zed\SearchRankingStorage\Persistence\SpySearchRankingConfigurationStorageQuery;
use SprykerCommunity\Zed\SearchRankingStorage\Communication\Plugin\Synchronization\SearchRankingConfigurationSynchronizationDataPlugin;
use SprykerCommunity\Zed\SearchRankingStorage\Persistence\SearchRankingStorageEntityManager;

/**
 * INTEGRATION TEST — real Propel write, real Repository read, real plugin, no mocks. Closes the gap this
 * plugin previously had zero coverage of any kind: what actually reaches Elasticsearch/Redis via the
 * publish/synchronize pipeline is exactly whatever this plugin's `getData()` returns, so proving THAT is
 * correct is the right boundary to test at -- whether a `SynchronizationDataTransfer` really makes it onto
 * the queue and into storage from there is Spryker core's own `synchronization`/`synchronization-behavior`
 * packages' tested responsibility, not this feature package's; core's own storage-sync plugin tests (e.g.
 * `spryker/tax-product-storage`'s `TaxProductSynchronizationDataBulkRepositoryPluginTest`) stop at the same
 * boundary for the identical reason.
 *
 * Uses a deliberately fake store/locale pair (`ZZ-SYNC-PLUGIN-TEST`/`xx_XX`) so this can never collide with
 * or corrupt a real store's live ranking configuration. `TransactionHelper` (added to this suite's own
 * `codeception.yml` alongside this test -- it previously had no Propel bootstrap at all, unlike the
 * `SearchRankingOptimizer` suite) wraps every test in a transaction and rolls it back afterwards, so no
 * manual cleanup is needed.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRankingStorage
 * @group Communication
 * @group Plugin
 * @group Synchronization
 * @group SearchRankingConfigurationSynchronizationDataPluginTest
 * @group NeedsDatabase
 */
class SearchRankingConfigurationSynchronizationDataPluginTest extends Unit
{
    /**
     * @var string
     */
    protected const STORE_NAME = 'ZZ-SYNC-PLUGIN-TEST';

    /**
     * @var string
     */
    protected const LOCALE_NAME = 'xx_XX';

    public function testGetDataReturnsTheRealPersistedConfigurationForTheSavedStoreAndLocale(): void
    {
        // Arrange
        $configurationData = [
            'metric_weights' => ['top_seller' => 0.6, 'pdp_impressions' => 0.4],
            'relevance_weight' => 0.75,
            'relevance_saturation_point' => 12.5,
        ];
        (new SearchRankingStorageEntityManager())->saveRankingConfiguration($configurationData, static::STORE_NAME, static::LOCALE_NAME);

        $storageEntity = (new SpySearchRankingConfigurationStorageQuery())
            ->filterByStore(static::STORE_NAME)
            ->filterByLocale(static::LOCALE_NAME)
            ->findOne();
        $this->assertNotNull($storageEntity, 'Setup: the row must have actually been written before the plugin can be expected to find it.');

        // Act
        $synchronizationDataTransfers = (new SearchRankingConfigurationSynchronizationDataPlugin())->getData(0, 100);
        $matching = $this->findTransferForOurTestRow($synchronizationDataTransfers);

        // Assert
        $this->assertNotNull($matching, 'The plugin must surface the row this test just wrote among the synchronization data.');
        $this->assertSame(static::STORE_NAME, $matching->getStore());
        $this->assertSame(static::LOCALE_NAME, $matching->getLocale());
        $this->assertSame(
            $storageEntity->getKey(),
            $matching->getKey(),
            'The synchronization key must be exactly what the storage behavior itself computed, not re-derived by the plugin.',
        );
        $this->assertSame($configurationData, $matching->getData());
    }

    /**
     * The `$ids` filter (used by a targeted resync, e.g. after a single metric save) must narrow the
     * result to exactly that row -- proven against a REAL second, different row so an unfiltered/broken
     * filter would be caught rather than trivially passing on an empty table.
     */
    public function testGetDataFiltersByIdsWhenGiven(): void
    {
        // Arrange
        $entityManager = new SearchRankingStorageEntityManager();
        $entityManager->saveRankingConfiguration(['relevance_weight' => 0.5], static::STORE_NAME, static::LOCALE_NAME);
        $entityManager->saveRankingConfiguration(['relevance_weight' => 0.9], static::STORE_NAME, 'yy_YY');

        $ourRowId = (new SpySearchRankingConfigurationStorageQuery())
            ->filterByStore(static::STORE_NAME)
            ->filterByLocale(static::LOCALE_NAME)
            ->findOne()
            ->getIdSearchRankingConfigurationStorage();

        // Act
        $synchronizationDataTransfers = (new SearchRankingConfigurationSynchronizationDataPlugin())->getData(0, 100, [$ourRowId]);

        // Assert
        $this->assertCount(1, $synchronizationDataTransfers);
        $this->assertSame(static::LOCALE_NAME, $synchronizationDataTransfers[0]->getLocale());
    }

    /**
     * @param array<\Generated\Shared\Transfer\SynchronizationDataTransfer> $synchronizationDataTransfers
     */
    protected function findTransferForOurTestRow(array $synchronizationDataTransfers): ?SynchronizationDataTransfer
    {
        foreach ($synchronizationDataTransfers as $synchronizationDataTransfer) {
            if ($synchronizationDataTransfer->getStore() === static::STORE_NAME && $synchronizationDataTransfer->getLocale() === static::LOCALE_NAME) {
                return $synchronizationDataTransfer;
            }
        }

        return null;
    }
}
