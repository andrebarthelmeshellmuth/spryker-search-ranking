<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

class ScopeCopyLockValidator implements ScopeCopyLockValidatorInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
    ) {
    }

    /**
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     */
    public function validate(string $sourceStoreName, string $sourceLocaleName, string $targetStoreName, string $targetLocaleName): ?string
    {
        if ($sourceStoreName === $targetStoreName && $sourceLocaleName === $targetLocaleName) {
            return 'Source and target scope must be different.';
        }

        $activeLockTransfers = $this->repository->getActiveScopeCopyLocks();

        foreach ($activeLockTransfers as $activeLockTransfer) {
            if ($this->isScope($activeLockTransfer->getTargetStoreNameOrFail(), $activeLockTransfer->getTargetLocaleNameOrFail(), $targetStoreName, $targetLocaleName)) {
                return sprintf(
                    '%s/%s is already the target of an active lock (from %s/%s) — a scope may be the target of at most one active lock.',
                    $targetStoreName,
                    $targetLocaleName,
                    $activeLockTransfer->getSourceStoreNameOrFail(),
                    $activeLockTransfer->getSourceLocaleNameOrFail(),
                );
            }

            if ($this->isScope($activeLockTransfer->getSourceStoreNameOrFail(), $activeLockTransfer->getSourceLocaleNameOrFail(), $targetStoreName, $targetLocaleName)) {
                return sprintf(
                    '%s/%s is already the source of an active lock (to %s/%s) and cannot become a target at the same time.',
                    $targetStoreName,
                    $targetLocaleName,
                    $activeLockTransfer->getTargetStoreNameOrFail(),
                    $activeLockTransfer->getTargetLocaleNameOrFail(),
                );
            }

            if ($this->isScope($activeLockTransfer->getTargetStoreNameOrFail(), $activeLockTransfer->getTargetLocaleNameOrFail(), $sourceStoreName, $sourceLocaleName)) {
                return sprintf(
                    '%s/%s is currently the target of an active lock (from %s/%s) and cannot become a source at the same time.',
                    $sourceStoreName,
                    $sourceLocaleName,
                    $activeLockTransfer->getSourceStoreNameOrFail(),
                    $activeLockTransfer->getSourceLocaleNameOrFail(),
                );
            }
        }

        return null;
    }

    /**
     * @param string $storeName
     * @param string $localeName
     * @param string $comparedStoreName
     * @param string $comparedLocaleName
     */
    protected function isScope(string $storeName, string $localeName, string $comparedStoreName, string $comparedLocaleName): bool
    {
        return $storeName === $comparedStoreName && $localeName === $comparedLocaleName;
    }
}
