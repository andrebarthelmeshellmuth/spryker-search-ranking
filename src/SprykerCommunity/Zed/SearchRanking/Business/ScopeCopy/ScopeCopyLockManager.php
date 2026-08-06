<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

class ScopeCopyLockManager implements ScopeCopyLockManagerInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockValidatorInterface $lockValidator
     * @param \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopierInterface $scopeConfigCopier
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingEntityManagerInterface $entityManager,
        protected ScopeCopyLockValidatorInterface $lockValidator,
        protected ScopeConfigCopierInterface $scopeConfigCopier,
    ) {
    }

    /**
     * @return array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer>
     */
    public function getActiveScopeCopyLocks(): array
    {
        return $this->repository->getActiveScopeCopyLocks();
    }

    /**
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param bool $confirmOverwrite
     */
    public function createScopeCopyLock(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        bool $confirmOverwrite,
    ): SearchRankingScopeCopyResultTransfer {
        $validationError = $this->lockValidator->validate($sourceStoreName, $sourceLocaleName, $targetStoreName, $targetLocaleName);

        if ($validationError !== null) {
            return (new SearchRankingScopeCopyResultTransfer())
                ->setIsSuccess(false)
                ->setErrorMessage($validationError);
        }

        $copyResultTransfer = $this->scopeConfigCopier->copyScopeConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $confirmOverwrite,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        if (!$copyResultTransfer->getIsSuccess()) {
            return $copyResultTransfer;
        }

        $this->entityManager->createScopeCopyLock(
            (new SearchRankingScopeCopyLockTransfer())
                ->setSourceStoreName($sourceStoreName)
                ->setSourceLocaleName($sourceLocaleName)
                ->setTargetStoreName($targetStoreName)
                ->setTargetLocaleName($targetLocaleName)
                ->setIsActive(true),
        );

        return $copyResultTransfer;
    }

    /**
     * @param int $idSearchRankingScopeCopyLock
     */
    public function deactivateScopeCopyLock(int $idSearchRankingScopeCopyLock): void
    {
        $this->entityManager->deactivateScopeCopyLock($idSearchRankingScopeCopyLock);
    }

    public function runDailySync(): int
    {
        $syncedCount = 0;

        foreach ($this->repository->getActiveScopeCopyLocks() as $activeLockTransfer) {
            $this->scopeConfigCopier->copyScopeConfiguration(
                $activeLockTransfer->getSourceStoreNameOrFail(),
                $activeLockTransfer->getSourceLocaleNameOrFail(),
                $activeLockTransfer->getTargetStoreNameOrFail(),
                $activeLockTransfer->getTargetLocaleNameOrFail(),
                true,
                SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
            );
            $syncedCount++;
        }

        return $syncedCount;
    }
}
