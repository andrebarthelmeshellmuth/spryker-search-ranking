<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Console;

use Generated\Shared\Transfer\SearchRankingEngineCapabilityTransfer;
use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 */
class SearchRankingCheckCompatibilityConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-ranking:check-compatibility';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Probes the live search engine\'s actual capabilities (never a version-string comparison) for the constructs this package uses today or could use in a future phase.';

    /**
     * The construct this package's live `function_score` ranking (phase 3) actually depends on today —
     * if the engine fails THIS one, the shop's ranking feature is currently broken, so the command exits
     * non-zero. Every other probed capability is purely forward-looking (future roadmap phases) and never
     * affects the exit code.
     *
     * @var string
     */
    protected const CAPABILITY_IN_PRODUCTION_USE = 'function_score + script_score (painless)';

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
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $input is mandated by the Console base class.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $compatibilityTransfer = $this->getFacade()->checkEngineCompatibility();

        $output->writeln(sprintf(
            'Engine: %s %s',
            $compatibilityTransfer->getDistributionOrFail(),
            $compatibilityTransfer->getVersionOrFail(),
        ));
        $output->writeln('');

        $isProductionCapabilityBroken = false;
        // Printed once, right before the FIRST optional capability line — a blank line plus a single
        // headline covering the whole block of optional ones below, rather than repeating the same note
        // on every one of their individual lines.
        $hasPrintedOptionalHeadline = false;

        foreach ($compatibilityTransfer->getCapabilities() as $capabilityTransfer) {
            $isRequired = $capabilityTransfer->getNameOrFail() === static::CAPABILITY_IN_PRODUCTION_USE;

            if (!$isRequired && !$hasPrintedOptionalHeadline) {
                $output->writeln('');
                $output->writeln('<comment>Optional — forward-looking only, does not affect this package\'s current version and therefore this command\'s exit code:</comment>');
                $hasPrintedOptionalHeadline = true;
            }

            $this->writeCapabilityLine($output, $capabilityTransfer);

            if (!$isRequired) {
                continue;
            }

            if ($capabilityTransfer->getIsSupportedOrFail()) {
                continue;
            }

            $isProductionCapabilityBroken = true;
        }

        if ($isProductionCapabilityBroken) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>"%s" is NOT supported by this engine — the live function_score ranking (phase 3) is broken.</error>',
                static::CAPABILITY_IN_PRODUCTION_USE,
            ));

            return static::CODE_ERROR;
        }

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Generated\Shared\Transfer\SearchRankingEngineCapabilityTransfer $capabilityTransfer
     *
     * @return void
     */
    protected function writeCapabilityLine(OutputInterface $output, SearchRankingEngineCapabilityTransfer $capabilityTransfer): void
    {
        $marker = $capabilityTransfer->getIsSupportedOrFail() ? '<info>✓</info>' : '<comment>✗</comment>';

        $output->writeln(sprintf(
            '%s %s — %s',
            $marker,
            $capabilityTransfer->getNameOrFail(),
            $capabilityTransfer->getDetailOrFail(),
        ));
    }
}
