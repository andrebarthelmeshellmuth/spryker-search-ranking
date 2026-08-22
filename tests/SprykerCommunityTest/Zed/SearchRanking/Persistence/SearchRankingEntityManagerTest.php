<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Persistence;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingScopeCopyLockQuery;
use SprykerCommunity\Zed\SearchRanking\Persistence\Exception\ConcurrentScopeCopyLockException;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManager;

/**
 * INTEGRATION TEST — real database, real rows, never mocked: the one behavior actually worth protecting
 * is the `active_target_scope_key` concurrency guard (a real DB-enforced UNIQUE index, see the entity
 * manager's own docblock), which no mocked query builder could confirm.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Persistence
 * @group SearchRankingEntityManagerTest
 * @group NeedsDatabase
 */
class SearchRankingEntityManagerTest extends Unit
{
    /**
     * @var string
     */
    protected const TEST_SOURCE_STORE_NAME = 'PHPUNIT_SOURCE';

    /**
     * @var string
     */
    protected const TEST_TARGET_STORE_NAME = 'PHPUNIT_TARGET';

    /**
     * @var string
     */
    protected const TEST_LOCALE_NAME = 'phpunit_XX';

    protected function _before(): void
    {
        $this->deleteTestLocks();
    }

    protected function _after(): void
    {
        $this->deleteTestLocks();
    }

    public function testCreateScopeCopyLockPersistsAndReturnsTheGeneratedId(): void
    {
        $resultTransfer = (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(true));

        $this->assertNotNull($resultTransfer->getIdSearchRankingScopeCopyLock());

        $entity = SpySearchRankingScopeCopyLockQuery::create()->findOneByIdSearchRankingScopeCopyLock($resultTransfer->getIdSearchRankingScopeCopyLockOrFail());
        $this->assertNotNull($entity);
        $this->assertSame(static::TEST_TARGET_STORE_NAME, $entity->getTargetStoreName());
    }

    public function testCreateScopeCopyLockSetsActiveTargetScopeKeyWhenActive(): void
    {
        (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(true));

        $entity = SpySearchRankingScopeCopyLockQuery::create()
            ->filterByTargetStoreName(static::TEST_TARGET_STORE_NAME)
            ->findOne();

        $this->assertSame(
            static::TEST_TARGET_STORE_NAME . ':' . static::TEST_LOCALE_NAME,
            $entity->getActiveTargetScopeKey(),
        );
    }

    public function testCreateScopeCopyLockLeavesActiveTargetScopeKeyNullWhenNotActive(): void
    {
        (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(false));

        $entity = SpySearchRankingScopeCopyLockQuery::create()
            ->filterByTargetStoreName(static::TEST_TARGET_STORE_NAME)
            ->findOne();

        $this->assertNull($entity->getActiveTargetScopeKey());
    }

    public function testCreateScopeCopyLockThrowsConcurrentScopeCopyLockExceptionWhenAnActiveLockAlreadyTargetsTheSameScope(): void
    {
        (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(true));

        $this->expectException(ConcurrentScopeCopyLockException::class);

        (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(true));
    }

    public function testCreateScopeCopyLockDoesNotThrowWhenAPriorLockForTheSameTargetIsAlreadyInactive(): void
    {
        (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(false));

        $resultTransfer = (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(true));

        $this->assertNotNull($resultTransfer->getIdSearchRankingScopeCopyLock());
    }

    public function testDeactivateScopeCopyLockClearsActiveTargetScopeKey(): void
    {
        $created = (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(true));

        (new SearchRankingEntityManager())->deactivateScopeCopyLock($created->getIdSearchRankingScopeCopyLockOrFail());

        $entity = SpySearchRankingScopeCopyLockQuery::create()->findOneByIdSearchRankingScopeCopyLock($created->getIdSearchRankingScopeCopyLockOrFail());
        $this->assertNull($entity->getActiveTargetScopeKey());
    }

    public function testDeactivateScopeCopyLockAllowsANewActiveLockForTheSameTargetAfterward(): void
    {
        // Proves the concurrency guard genuinely releases once a lock is deactivated -- not just that the
        // column reads null, but that a real second INSERT succeeds where it would otherwise collide.
        $created = (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(true));
        (new SearchRankingEntityManager())->deactivateScopeCopyLock($created->getIdSearchRankingScopeCopyLockOrFail());

        $resultTransfer = (new SearchRankingEntityManager())->createScopeCopyLock($this->createLockTransfer(true));

        $this->assertNotNull($resultTransfer->getIdSearchRankingScopeCopyLock());
    }

    protected function createLockTransfer(bool $isActive): SearchRankingScopeCopyLockTransfer
    {
        return (new SearchRankingScopeCopyLockTransfer())
            ->setSourceStoreName(static::TEST_SOURCE_STORE_NAME)
            ->setSourceLocaleName(static::TEST_LOCALE_NAME)
            ->setTargetStoreName(static::TEST_TARGET_STORE_NAME)
            ->setTargetLocaleName(static::TEST_LOCALE_NAME)
            ->setIsActive($isActive);
    }

    protected function deleteTestLocks(): void
    {
        SpySearchRankingScopeCopyLockQuery::create()
            ->filterByTargetStoreName(static::TEST_TARGET_STORE_NAME)
            ->delete();
    }
}
