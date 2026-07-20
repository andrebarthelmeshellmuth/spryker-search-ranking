<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Calibration;

use Generated\Shared\Transfer\SearchRankingCalibrationSearchTermTransfer;
use Generated\Shared\Transfer\SearchRankingCalibrationTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;

class CalibrationUploadHandler implements CalibrationUploadHandlerInterface
{
    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Business\Calibration\CsvSearchTermParserInterface
     */
    protected CsvSearchTermParserInterface $csvSearchTermParser;

    /**
     * @var \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface
     */
    protected SearchRankingEntityManagerInterface $entityManager;

    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Calibration\CsvSearchTermParserInterface $csvSearchTermParser
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     */
    public function __construct(
        CsvSearchTermParserInterface $csvSearchTermParser,
        SearchRankingEntityManagerInterface $entityManager,
    ) {
        $this->csvSearchTermParser = $csvSearchTermParser;
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritDoc}
     *
     * @param int $relevantProductCount
     * @param string $storeName
     * @param string $localeName
     * @param string $csvContent
     *
     * @return \Generated\Shared\Transfer\SearchRankingCalibrationTransfer
     */
    public function createCalibration(int $relevantProductCount, string $storeName, string $localeName, string $csvContent): SearchRankingCalibrationTransfer
    {
        $calibrationTransfer = (new SearchRankingCalibrationTransfer())
            ->setRelevantProductCount($relevantProductCount)
            ->setStoreName($storeName)
            ->setLocaleName($localeName)
            ->setStatus(SearchRankingConfig::CALIBRATION_STATUS_UPLOADED);

        foreach ($this->csvSearchTermParser->parse($csvContent) as $searchTerm) {
            $calibrationTransfer->addSearchTerm(
                (new SearchRankingCalibrationSearchTermTransfer())->setSearchTerm($searchTerm),
            );
        }

        return $this->entityManager->createCalibration($calibrationTransfer);
    }
}
