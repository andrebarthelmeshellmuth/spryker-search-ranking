<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy;

use Generated\Shared\Transfer\SearchRankingStoreConfigCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigPreviewTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

class StoreConfigCopier implements StoreConfigCopierInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface $metricWriter
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected MetricWriterInterface $metricWriter,
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
    public function copyStoreConfiguration(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
        string $mode,
        bool $confirmOverwrite,
        string $changeSource,
    ): SearchRankingStoreConfigCopyResultTransfer {
        $resultTransfer = new SearchRankingStoreConfigCopyResultTransfer();

        if ($sourceStoreName === $targetStoreName) {
            return $resultTransfer
                ->setIsSuccess(false)
                ->setErrorMessage('Source and target store must be different.');
        }

        if (!$confirmOverwrite && $this->hasStoreConfiguration($targetStoreName)) {
            return $resultTransfer->setIsBlockedByExistingData(true);
        }

        $copiedCount = 0;
        $skippedCount = 0;

        foreach ($this->repository->getMetricCollection($sourceStoreName, $sourceLocaleName)->getMetrics() as $metricTransfer) {
            if ($metricTransfer->getFormula() === null) {
                // Never explicitly configured for the source store at all — nothing to copy, same
                // "absence means untouched" convention weight/settings copying already uses.
                continue;
            }

            $idSearchRankingMetric = $metricTransfer->getIdSearchRankingMetricOrFail();

            if (
                $mode === static::MODE_COPY_ONLY_OVERLAP
                && $this->repository->findMetricStoreConfig($idSearchRankingMetric, $targetStoreName) === null
            ) {
                $skippedCount++;

                continue;
            }

            $this->metricWriter->saveMetric($metricTransfer->setChangeSource($changeSource), $targetStoreName, $targetLocaleName);
            $copiedCount++;
        }

        return $resultTransfer
            ->setIsSuccess(true)
            ->setCopiedCount($copiedCount)
            ->setSkippedCount($skippedCount);
    }

    /**
     * @param string $storeName
     */
    public function hasStoreConfiguration(string $storeName): bool
    {
        return $this->repository->hasStoreConfiguration($storeName);
    }

    /**
     * The locale passed to {@see \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface::getMetricCollection()}
     * here is a pure API artifact (it needs one to return a metric list at all) — formula/isActive are
     * store-wide, so any real locale of the store returns identical results; the app-wide default is
     * used rather than asking the caller for one it has no actual use for.
     *
     * @param string $sourceStoreName
     */
    public function previewStoreConfiguration(string $sourceStoreName): SearchRankingStoreConfigPreviewTransfer
    {
        $metrics = [];

        foreach ($this->repository->getMetricCollection($sourceStoreName, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME)->getMetrics() as $metricTransfer) {
            $storeConfigTransfer = $this->repository->findMetricStoreConfig($metricTransfer->getIdSearchRankingMetricOrFail(), $sourceStoreName);

            if ($storeConfigTransfer === null) {
                continue;
            }

            $metrics[$metricTransfer->getNameOrFail()] = [
                'formula' => $storeConfigTransfer->getFormula(),
                'isActive' => $storeConfigTransfer->getIsActive(),
            ];
        }

        return (new SearchRankingStoreConfigPreviewTransfer())->setMetrics($metrics);
    }
}
