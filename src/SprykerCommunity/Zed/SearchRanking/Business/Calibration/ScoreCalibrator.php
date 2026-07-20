<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Calibration;

use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\SearchRankingCalibrationNotFoundException;
use SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;
use Throwable;

/**
 * Fires the calibration query for every uploaded search term directly against Elasticsearch — via
 * `SearchRankingToSearchRankingClientInterface::getCalibrationScores()`, which bypasses
 * `Client\Catalog`/`Client\Search` entirely (both throw when called from Zed in this shop; see
 * `SprykerCommunity\Client\SearchRanking\Search\CalibrationSearcher` for the full story) — so the sampled
 * scores are faithful to production regardless of whether a `function_score` wrapper happens to be wired
 * in at the time; the raw text-relevance score is recovered either way.
 */
class ScoreCalibrator implements ScoreCalibratorInterface
{
    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface
     */
    protected SearchRankingRepositoryInterface $repository;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface
     */
    protected SearchRankingEntityManagerInterface $entityManager;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface
     */
    protected SearchRankingToSearchRankingClientInterface $searchRankingClient;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Business\Calibration\StatisticsCalculatorInterface
     */
    protected StatisticsCalculatorInterface $statisticsCalculator;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Client\SearchRankingToSearchRankingClientInterface $searchRankingClient
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Calibration\StatisticsCalculatorInterface $statisticsCalculator
     */
    public function __construct(
        SearchRankingRepositoryInterface $repository,
        SearchRankingEntityManagerInterface $entityManager,
        SearchRankingToSearchRankingClientInterface $searchRankingClient,
        StatisticsCalculatorInterface $statisticsCalculator,
    ) {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
        $this->searchRankingClient = $searchRankingClient;
        $this->statisticsCalculator = $statisticsCalculator;
    }

    /**
     * {@inheritDoc}
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer|null
     */
    public function runNextCalibration(): ?SearchRankingCalibrationTransfer
    {
        $uploadedCalibrations = $this->repository->getUploadedCalibrations();

        if ($uploadedCalibrations === []) {
            return null;
        }

        $newestCalibration = array_shift($uploadedCalibrations);

        foreach ($uploadedCalibrations as $staleCalibrationTransfer) {
            $this->entityManager->updateCalibrationStatus(
                $staleCalibrationTransfer->getIdSearchRankingCalibrationOrFail(),
                SearchRankingConfig::CALIBRATION_STATUS_SKIPPED,
            );
        }

        return $this->calculate($newestCalibration->getIdSearchRankingCalibrationOrFail());
    }

    /**
     * @param int $idSearchRankingCalibration
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer
     */
    protected function calculate(int $idSearchRankingCalibration): SearchRankingCalibrationTransfer
    {
        $this->entityManager->updateCalibrationStatus($idSearchRankingCalibration, SearchRankingConfig::CALIBRATION_STATUS_CALCULATING);

        $calibrationTransfer = $this->requireCalibration($idSearchRankingCalibration);
        $limit = $calibrationTransfer->getRelevantProductCountOrFail();
        $storeName = $calibrationTransfer->getStoreNameOrFail();
        $localeName = $calibrationTransfer->getLocaleNameOrFail();
        $pooledScores = [];

        foreach ($calibrationTransfer->getSearchTerms() as $searchTermTransfer) {
            $scores = $this->fetchScoresForSearchTerm($searchTermTransfer->getSearchTermOrFail(), $storeName, $localeName, $limit);

            $this->entityManager->saveCalibrationSearchTermResult(
                $searchTermTransfer->getIdSearchRankingCalibrationSearchTermOrFail(),
                count($scores),
                $scores,
            );

            array_push($pooledScores, ...$scores);
        }

        if ($pooledScores === []) {
            $this->entityManager->markCalibrationFailed(
                $idSearchRankingCalibration,
                'No product scores were found for any uploaded search term.',
            );
        } else {
            $this->entityManager->saveCalibrationStatistics(
                $idSearchRankingCalibration,
                $this->statisticsCalculator->calculate($pooledScores),
            );
        }

        return $this->requireCalibration($idSearchRankingCalibration);
    }

    /**
     * @param int $idSearchRankingCalibration
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\SearchRankingCalibrationNotFoundException
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer
     */
    protected function requireCalibration(int $idSearchRankingCalibration): SearchRankingCalibrationTransfer
    {
        $calibrationTransfer = $this->repository->findCalibrationWithSearchTerms($idSearchRankingCalibration);

        if ($calibrationTransfer === null) {
            throw new SearchRankingCalibrationNotFoundException(sprintf(
                'Search ranking calibration with id "%d" was not found.',
                $idSearchRankingCalibration,
            ));
        }

        return $calibrationTransfer;
    }

    /**
     * A single search term's Elasticsearch call failing is caught here and treated as 0 products found
     * for that term — it must not abort the rest of the calibration run.
     *
     * @param string $searchTerm
     * @param string $storeName
     * @param string $localeName
     * @param int $limit
     *
     * @return array<float>
     */
    protected function fetchScoresForSearchTerm(string $searchTerm, string $storeName, string $localeName, int $limit): array
    {
        try {
            return $this->searchRankingClient->getCalibrationScores($searchTerm, $storeName, $localeName, $limit);
        } catch (Throwable $exception) {
            return [];
        }
    }
}
