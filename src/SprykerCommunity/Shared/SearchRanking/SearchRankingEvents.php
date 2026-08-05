<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchRanking;

interface SearchRankingEvents
{
    /**
     * Specification:
     * - Fired synchronously, in-process, every time ranking configuration changes: a relevance/
     *   specificity setting (weight, saturation point) or a metric's identity/weight/deletion —
     *   regardless of which write path made the change (Zed GUI, or an automated caller like
     *   search-ranking-optimizer applying a run/checkpoint/calibration).
     * - A project installing this package must register a synchronous (non-queued) listener for this
     *   event in its own `Pyz\Zed\Event\EventDependencyProvider` for the change to actually reach the
     *   synced key-value storage the live storefront reads — see this package's README.
     *
     * @api
     *
     * @var string
     */
    public const RANKING_CONFIGURATION_CHANGE = 'SearchRanking.ranking_configuration.change';
}
