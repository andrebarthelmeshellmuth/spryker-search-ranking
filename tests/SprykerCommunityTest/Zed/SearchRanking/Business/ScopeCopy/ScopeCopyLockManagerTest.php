<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\ScopeCopy;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingFullScopeCopyResultTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\FullScopeCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockManager;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockValidatorInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\Exception\ConcurrentScopeCopyLockException;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group ScopeCopy
 * @group ScopeCopyLockManagerTest
 * @group Portable
 */
class ScopeCopyLockManagerTest extends Unit
{
    public function testCreateScopeCopyLockReturnsTheValidationErrorWithoutCopyingOrPersistingAnything(): void
    {
        $lockValidatorMock = $this->createMock(ScopeCopyLockValidatorInterface::class);
        $lockValidatorMock->method('validate')->willReturn('Source and target scope must be different.');
        $fullScopeCopierMock = $this->createMock(FullScopeCopierInterface::class);
        $fullScopeCopierMock->expects($this->never())->method('copyFullScopeConfiguration');
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('createScopeCopyLock');

        $resultTransfer = $this->createManager($lockValidatorMock, $fullScopeCopierMock, $entityManagerMock)
            ->createScopeCopyLock('DE', 'de_DE', 'DE', 'de_DE', false);

        $this->assertFalse($resultTransfer->getIsSuccess());
        $this->assertSame('Source and target scope must be different.', $resultTransfer->getErrorMessage());
    }

    public function testCreateScopeCopyLockPersistsTheLockOnceTheCopySucceeds(): void
    {
        $copyResultTransfer = (new SearchRankingFullScopeCopyResultTransfer())->setIsSuccess(true);
        $fullScopeCopierMock = $this->createMock(FullScopeCopierInterface::class);
        $fullScopeCopierMock->method('copyFullScopeConfiguration')->willReturn($copyResultTransfer);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('createScopeCopyLock');

        $resultTransfer = $this->createManager($this->passingValidatorMock(), $fullScopeCopierMock, $entityManagerMock)
            ->createScopeCopyLock('DE', 'de_DE', 'AT', 'de_AT', true);

        $this->assertTrue($resultTransfer->getIsSuccess());
    }

    public function testCreateScopeCopyLockNeverPersistsALockWhenTheCopyItselfFails(): void
    {
        $copyResultTransfer = (new SearchRankingFullScopeCopyResultTransfer())->setIsSuccess(false)->setErrorMessage('boom');
        $fullScopeCopierMock = $this->createMock(FullScopeCopierInterface::class);
        $fullScopeCopierMock->method('copyFullScopeConfiguration')->willReturn($copyResultTransfer);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->expects($this->never())->method('createScopeCopyLock');

        $resultTransfer = $this->createManager($this->passingValidatorMock(), $fullScopeCopierMock, $entityManagerMock)
            ->createScopeCopyLock('DE', 'de_DE', 'AT', 'de_AT', true);

        $this->assertFalse($resultTransfer->getIsSuccess());
    }

    /**
     * The validator's own read-then-write check cannot close the race between two concurrent calls
     * locking the SAME target scope (see ScopeCopyLockValidator's own docblock) — this is the database's
     * active_target_scope_key unique index rejecting the losing request's insert, surfaced here as a
     * normal isSuccess=false result rather than an uncaught exception.
     */
    public function testCreateScopeCopyLockReturnsAFailureResultWhenTheEntityManagerRejectsAConcurrentLock(): void
    {
        $copyResultTransfer = (new SearchRankingFullScopeCopyResultTransfer())->setIsSuccess(true);
        $fullScopeCopierMock = $this->createMock(FullScopeCopierInterface::class);
        $fullScopeCopierMock->method('copyFullScopeConfiguration')->willReturn($copyResultTransfer);
        $entityManagerMock = $this->createMock(SearchRankingEntityManagerInterface::class);
        $entityManagerMock->method('createScopeCopyLock')->willThrowException(
            new ConcurrentScopeCopyLockException('AT/de_AT is already the target of an active lock.'),
        );

        $resultTransfer = $this->createManager($this->passingValidatorMock(), $fullScopeCopierMock, $entityManagerMock)
            ->createScopeCopyLock('DE', 'de_DE', 'AT', 'de_AT', true);

        $this->assertFalse($resultTransfer->getIsSuccess());
        $this->assertSame('AT/de_AT is already the target of an active lock.', $resultTransfer->getErrorMessage());
    }

    protected function passingValidatorMock(): ScopeCopyLockValidatorInterface
    {
        $lockValidatorMock = $this->createMock(ScopeCopyLockValidatorInterface::class);
        $lockValidatorMock->method('validate')->willReturn(null);

        return $lockValidatorMock;
    }

    protected function createManager(
        ScopeCopyLockValidatorInterface $lockValidator,
        FullScopeCopierInterface $fullScopeCopier,
        SearchRankingEntityManagerInterface $entityManager,
    ): ScopeCopyLockManager {
        return new ScopeCopyLockManager(
            $this->createMock(SearchRankingRepositoryInterface::class),
            $entityManager,
            $lockValidator,
            $this->createMock(ScopeConfigCopierInterface::class),
            $fullScopeCopier,
        );
    }
}
