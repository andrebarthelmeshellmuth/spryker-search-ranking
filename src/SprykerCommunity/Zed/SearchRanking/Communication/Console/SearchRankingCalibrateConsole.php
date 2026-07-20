<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface getRepository()
 */
class SearchRankingCalibrateConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-ranking:calibrate';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Picks up the newest uploaded search-ranking calibration run (skipping any older uploads), fires the live catalog search-string query for each of its search terms, and pools the resulting raw text-relevance scores into a suggested relevanceSaturationPoint (k) value.';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);

        parent::configure();
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- signature is fixed by the Console base class.
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
        $calibrationTransfer = $this->getFacade()->runNextCalibration();

        if ($calibrationTransfer === null) {
            $output->writeln('No uploaded calibration run found.');

            return static::CODE_SUCCESS;
        }

        if ($calibrationTransfer->getStatus() === SearchRankingConfig::CALIBRATION_STATUS_FAILED) {
            $output->writeln(sprintf('<error>%s</error>', $calibrationTransfer->getErrorMessage()));

            return static::CODE_ERROR;
        }

        $output->writeln(sprintf(
            'Calibration #%d done: sampled %d score(s) across %d search term(s), computed k = %.4f.',
            $calibrationTransfer->getIdSearchRankingCalibrationOrFail(),
            $calibrationTransfer->getSampleCountOrFail(),
            count($calibrationTransfer->getSearchTerms()),
            $calibrationTransfer->getComputedKOrFail(),
        ));

        return static::CODE_SUCCESS;
    }
}
