<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 */
class SearchRankingScopeCopySyncConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-ranking:scope-copy-sync';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Re-copies every active scope-copy lock\'s source scope onto its target scope. Intended for the daily cron.';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);

        parent::configure();
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- $input is unused (this command takes no arguments/options) but required by Console::execute()'s own signature.
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
        $syncedCount = $this->getFacade()->runScopeCopyDailySync();

        if ($syncedCount === 0) {
            $output->writeln('No active scope-copy locks — nothing to do.');

            return static::CODE_SUCCESS;
        }

        $output->writeln(sprintf('Synced %d active scope-copy lock(s).', $syncedCount));

        return static::CODE_SUCCESS;
    }
}
