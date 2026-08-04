<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 */
class SearchRankingRandomizeConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-ranking:randomize';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Reshuffles the random tie-breaker metric\'s values and republishes affected products. Does nothing if that metric is inactive or does not exist.';

    /**
     * @var string
     */
    public const OPTION_STORE = 'store';

    /**
     * @var string
     */
    public const OPTION_LOCALE = 'locale';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);
        $this->addOption(
            static::OPTION_STORE,
            null,
            InputOption::VALUE_REQUIRED,
            'Restrict to this store only. Omit to process every store.',
        );
        $this->addOption(
            static::OPTION_LOCALE,
            null,
            InputOption::VALUE_REQUIRED,
            'Restrict to this locale only. Omit to process every locale available for the selected store(s).',
        );

        parent::configure();
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->getFacade()->randomizeRandomMetricIfActive($input->getOption(static::OPTION_STORE), $input->getOption(static::OPTION_LOCALE))) {
            $output->writeln('Random tie-breaker metric is not active (or does not exist) — nothing to do.');

            return static::CODE_SUCCESS;
        }

        $output->writeln('Reshuffled the random tie-breaker metric and republished affected products.');

        return static::CODE_SUCCESS;
    }
}
