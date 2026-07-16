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
 * @method \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface getRepository()
 */
class SearchRankingNormalizeConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-ranking:normalize';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Recalculates the normalized ]0;1] ranking values of all active metrics from their raw product data using each metric\'s formula, then triggers publish events so the search documents pick up the fresh scores.';

    /**
     * @var string
     */
    public const OPTION_SKIP_PUBLISH = 'skip-publish';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);
        $this->addOption(
            static::OPTION_SKIP_PUBLISH,
            null,
            InputOption::VALUE_NONE,
            'Only recalculate normalized values; do not trigger product abstract publish events.',
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
        $resultTransfer = $this->getFacade()->normalizeProductMetricValues();

        $output->writeln(sprintf(
            'Normalized %d row(s) across %d metric(s).',
            $resultTransfer->getUpdatedRowCount(),
            $resultTransfer->getProcessedMetricCount(),
        ));

        foreach ($resultTransfer->getErrors() as $error) {
            $output->writeln(sprintf('<error>%s</error>', $error));
        }

        if (!$input->getOption(static::OPTION_SKIP_PUBLISH)) {
            $publishedProductCount = $this->getFacade()->publishScoredProductAbstracts();

            $output->writeln(sprintf('Triggered publish events for %d product abstract(s).', $publishedProductCount));
        }

        return $resultTransfer->getErrors() === [] ? static::CODE_SUCCESS : static::CODE_ERROR;
    }
}
