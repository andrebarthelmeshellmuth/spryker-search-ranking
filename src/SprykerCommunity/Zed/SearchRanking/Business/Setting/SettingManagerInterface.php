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
    public function getRelevanceWeight(): float;

    /**
     * @param float $relevanceWeight
     *
     * @return void
     */
    public function saveRelevanceWeight(float $relevanceWeight): void;

    /**
     * @return float
     */
    public function getRelevanceSaturationPoint(): float;

    /**
     * @param float $relevanceSaturationPoint
     *
     * @return void
     */
    public function saveRelevanceSaturationPoint(float $relevanceSaturationPoint): void;

    /**
     * @return int
     */
    public function getEntropyProbeResultSize(): int;

    /**
     * @param int $entropyProbeResultSize
     *
     * @return void
     */
    public function saveEntropyProbeResultSize(int $entropyProbeResultSize): void;

    /**
     * @return float
     */
    public function getEntropyWeightExponent(): float;

    /**
     * @param float $entropyWeightExponent
     *
     * @return void
     */
    public function saveEntropyWeightExponent(float $entropyWeightExponent): void;

    /**
     * @return float
     */
    public function getEntropyWeightShiftMagnitude(): float;

    /**
     * @param float $entropyWeightShiftMagnitude
     *
     * @return void
     */
    public function saveEntropyWeightShiftMagnitude(float $entropyWeightShiftMagnitude): void;
}
