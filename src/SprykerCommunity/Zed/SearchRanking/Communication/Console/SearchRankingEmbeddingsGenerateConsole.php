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
class SearchRankingEmbeddingsGenerateConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-ranking:embeddings:generate';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Generates/refreshes semantic embedding vectors for product abstracts whose name/description changed since the last run. Skips (and reports) any product the embedding service fails to embed, rather than aborting the run.';

    /**
     * @var string
     */
    public const OPTION_STORE = 'store';

    /**
     * @var string
     */
    public const OPTION_LOCALE = 'locale';

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
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->getFacade()->generateEmbeddings(
            $input->getOption(static::OPTION_STORE),
            $input->getOption(static::OPTION_LOCALE),
        );

        $output->writeln(sprintf(
            'Generated: %d, skipped (unchanged): %d, failed: %d.',
            $result['generated'],
            $result['skipped'],
            $result['failed'],
        ));

        if ($result['failed'] > 0) {
            $output->writeln('Failures:');

            foreach ($result['failures'] as $idProductAbstract => $message) {
                $output->writeln(sprintf('  - product abstract %d: %s', $idProductAbstract, $message));
            }
        }

        return static::CODE_SUCCESS;
    }
}
