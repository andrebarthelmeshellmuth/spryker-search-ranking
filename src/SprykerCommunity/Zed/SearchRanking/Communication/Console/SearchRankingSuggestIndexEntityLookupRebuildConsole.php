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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Pass 2 of "Intent-Aware Alpha": full rebuild of the OpenSearch `completion`-suggester-backed entity
 * dictionary {@see \SprykerCommunity\Client\SearchRanking\Intent\SuggestIndexEntityLookup} reads at query
 * time — the large-adopter-scale sibling of `search-ranking:entity-lookup:rebuild` (which populates the
 * KV-backed tier instead). Reads the exact same underlying corpus, just writes it to a different place;
 * safe to run repeatedly.
 *
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 */
class SearchRankingSuggestIndexEntityLookupRebuildConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-ranking:entity-lookup:suggest-index:rebuild';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Rebuilds the OpenSearch completion-suggester-backed entity dictionary (Pass 2 of Intent-Aware Alpha) from the live catalog. Full rebuild only.';

    /**
     * @var string
     */
    public const OPTION_TYPE = 'type';

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
            static::OPTION_TYPE,
            null,
            InputOption::VALUE_REQUIRED,
            'Entity type to rebuild: "sku", "brand", or "category".',
            SearchRankingConfig::ENTITY_LOOKUP_TYPE_SKU,
        );
        $this->addOption(
            static::OPTION_STORE,
            null,
            InputOption::VALUE_REQUIRED,
            'Restrict to this store only. Omit to rebuild every store.',
        );
        $this->addOption(
            static::OPTION_LOCALE,
            null,
            InputOption::VALUE_REQUIRED,
            'Restrict category terms to this locale only. Has no filtering effect for --type=sku/brand.',
        );

        parent::configure();
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $writtenCountByStoreName = $this->getFacade()->rebuildSuggestIndexEntityLookup(
            (string)$input->getOption(static::OPTION_TYPE),
            $input->getOption(static::OPTION_STORE),
            $input->getOption(static::OPTION_LOCALE),
        );

        if ($writtenCountByStoreName === []) {
            $output->writeln('Nothing rebuilt — check --type, --store, and --locale.');

            return static::CODE_SUCCESS;
        }

        foreach ($writtenCountByStoreName as $storeName => $count) {
            $output->writeln(sprintf('%s: %d documents written.', $storeName, $count));
        }

        return static::CODE_SUCCESS;
    }
}
