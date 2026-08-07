<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Dependency\Facade;

use Spryker\Shared\Kernel\Transfer\TransferInterface;

interface SearchRankingToEventFacadeInterface
{
    /**
     * @param string $eventName
     * @param array<\Generated\Shared\Transfer\EventEntityTransfer> $eventEntityTransfers
     */
    public function triggerBulk(string $eventName, array $eventEntityTransfers): void;

    /**
     * @param string $eventName
     * @param \Spryker\Shared\Kernel\Transfer\TransferInterface $transfer
     */
    public function trigger(string $eventName, TransferInterface $transfer): void;

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function dumpEventListener(): array;
}
