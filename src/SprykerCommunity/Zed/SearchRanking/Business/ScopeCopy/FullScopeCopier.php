<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingFullScopeCopyPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFullScopeCopyResultTransfer;

class FullScopeCopier implements FullScopeCopierInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopierInterface $scopeConfigCopier
     * @param \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface $storeConfigCopier
     */
    public function __construct(
        protected ScopeConfigCopierInterface $scopeConfigCopier,
        protected StoreConfigCopierInterface $storeConfigCopier,
    ) {
    }

    /**
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     * @param string $mode
     * @param bool $confirmOverwrite
     * @param string $changeSource
     */
    public function copyFullScopeConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        string $mode,
        bool $confirmOverwrite,
        string $changeSource,
    ): SearchRankingFullScopeCopyResultTransfer {
        $resultTransfer = new SearchRankingFullScopeCopyResultTransfer();

        if ($sourceStoreName === $targetStoreName && $sourceLocaleName === $targetLocaleName) {
            return $resultTransfer
                ->setIsSuccess(false)
                ->setErrorMessage('Source and target scope must be different.');
        }

        if (!$confirmOverwrite && $this->hasFullScopeConfiguration($sourceStoreName, $sourceLocaleName, $targetStoreName, $targetLocaleName)) {
            return $resultTransfer->setIsBlockedByExistingData(true);
        }

        // $confirmOverwrite is forced true on both calls below: the real collision check already ran
        // above via hasFullScopeConfiguration(), so re-deriving it a second time inside each sub-copier's
        // own guard would be redundant and — if the two ever disagreed — could tear the combined write in
        // half (one side written, the other silently blocked).
        $scopeCopyResultTransfer = $this->scopeConfigCopier->copyScopeConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $mode,
            true,
            $changeSource,
        );

        $storeConfigCopyResultTransfer = null;

        if ($sourceStoreName !== $targetStoreName) {
            $storeConfigCopyResultTransfer = $this->storeConfigCopier->copyStoreConfiguration(
                $sourceStoreName,
                $sourceLocaleName,
                $targetStoreName,
                $targetLocaleName,
                $mode,
                true,
                $changeSource,
            );
        }

        return $resultTransfer
            ->setIsSuccess(true)
            ->setMetricWeightCopiedCount($scopeCopyResultTransfer->getMetricWeightCopiedCount())
            ->setSettingCopiedCount($scopeCopyResultTransfer->getSettingCopiedCount())
            ->setSkippedCount($scopeCopyResultTransfer->getSkippedCount())
            ->setStoreConfigCopiedCount($storeConfigCopyResultTransfer?->getCopiedCount() ?? 0)
            ->setStoreConfigSkippedCount($storeConfigCopyResultTransfer?->getSkippedCount() ?? 0);
    }

    /**
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     */
    public function hasFullScopeConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
    ): bool {
        if ($this->scopeConfigCopier->hasBlockingExistingData($sourceStoreName, $sourceLocaleName, $targetStoreName, $targetLocaleName)) {
            return true;
        }

        return $sourceStoreName !== $targetStoreName && $this->storeConfigCopier->hasStoreConfiguration($targetStoreName);
    }

    /**
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     */
    public function previewFullScopeConfiguration(string $sourceStoreName, string $sourceLocaleName): SearchRankingFullScopeCopyPreviewTransfer
    {
        $scopeCopyPreviewTransfer = $this->scopeConfigCopier->previewScopeConfiguration($sourceStoreName, $sourceLocaleName);
        $storeConfigPreviewTransfer = $this->storeConfigCopier->previewStoreConfiguration($sourceStoreName, $sourceLocaleName);

        return (new SearchRankingFullScopeCopyPreviewTransfer())
            ->setMetricWeights($scopeCopyPreviewTransfer->getMetricWeights())
            ->setSettings($scopeCopyPreviewTransfer->getSettings())
            ->setStoreConfigMetrics($storeConfigPreviewTransfer->getMetrics());
    }
}
