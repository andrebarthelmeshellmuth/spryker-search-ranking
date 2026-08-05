<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingStorage\Communication\Plugin\Event\Listener;

use Spryker\Shared\Kernel\Transfer\TransferInterface;
use Spryker\Zed\Event\Dependency\Plugin\EventHandlerInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;

/**
 * @method \SprykerCommunity\Zed\SearchRankingStorage\SearchRankingStorageConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Business\SearchRankingStorageFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRankingStorage\Communication\SearchRankingStorageCommunicationFactory getFactory()
 */
class RankingConfigurationPublishListener extends AbstractPlugin implements EventHandlerInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Spryker\Shared\Kernel\Transfer\TransferInterface $transfer
     * @param string $eventName
     *
     * @return void
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- signature is fixed by EventHandlerInterface.

    public function handle(TransferInterface $transfer, $eventName)
    {
        $this->getFacade()->publishRankingConfiguration();
    }

    // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
}
