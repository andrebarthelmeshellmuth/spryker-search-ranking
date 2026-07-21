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
     * @var \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface
     */
    protected SearchRankingGuiToSearchRankingFacadeInterface $searchRankingFacade;

    /**
     * @param \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface $searchRankingFacade
     */
    public function __construct(SearchRankingGuiToSearchRankingFacadeInterface $searchRankingFacade)
    {
        $this->searchRankingFacade = $searchRankingFacade;
    }

    /**
     * @param int|null $idSearchRankingMetric
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer|null
     */
    public function getData(?int $idSearchRankingMetric = null): ?SearchRankingMetricTransfer
    {
        if ($idSearchRankingMetric === null) {
            return (new SearchRankingMetricTransfer())->setIsActive(true)->setIsHigherBetter(true);
        }

        return $this->searchRankingFacade->findMetricById($idSearchRankingMetric);
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
