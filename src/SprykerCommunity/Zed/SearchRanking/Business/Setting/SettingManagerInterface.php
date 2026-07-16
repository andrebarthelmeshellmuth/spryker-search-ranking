<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Setting;

interface SettingManagerInterface
{
    /**
     * @return float
     */
    public function getScoreFloor(): float;

    /**
     * @param float $scoreFloor
     *
     * @return void
     */
    public function saveScoreFloor(float $scoreFloor): void;
}
