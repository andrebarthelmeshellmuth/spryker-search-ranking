<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form\DataProvider;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\MetricForm;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface;

class MetricFormDataProvider
{
    /**
     * @param \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface $searchRankingFacade
     */
    public function __construct(protected SearchRankingGuiToSearchRankingFacadeInterface $searchRankingFacade)
    {
    }

    /**
     * @param int|null $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function getData(?int $idSearchRankingMetric, string $storeName, string $localeName): ?SearchRankingMetricTransfer
    {
        if ($idSearchRankingMetric === null) {
            return (new SearchRankingMetricTransfer())->setIsActive(true)->setIsHigherBetter(true);
        }

        return $this->searchRankingFacade->findMetricById($idSearchRankingMetric, $storeName, $localeName);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return [
            MetricForm::OPTION_SEARCH_RANKING_FACADE => $this->searchRankingFacade,
        ];
    }
}
