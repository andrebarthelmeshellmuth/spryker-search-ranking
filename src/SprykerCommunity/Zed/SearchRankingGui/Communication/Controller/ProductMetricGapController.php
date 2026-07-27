<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class ProductMetricGapController extends AbstractController
{
    /**
     * @var string
     */
    protected const PARAM_METRIC = 'metric';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array<string, mixed>
     */
    public function indexAction(Request $request): array
    {
        $idSearchRankingMetric = $this->resolveIdSearchRankingMetric($request);

        return $this->viewResponse([
            'metrics' => $this->getFactory()->getSearchRankingFacade()->getActiveMetricCollection()->getMetrics(),
            'selectedIdSearchRankingMetric' => $idSearchRankingMetric,
            'productMetricGapTable' => $this->getFactory()->createProductMetricGapTable($idSearchRankingMetric)->render(),
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function tableAction(Request $request): JsonResponse
    {
        $idSearchRankingMetric = $this->resolveIdSearchRankingMetric($request);

        return $this->jsonResponse(
            $this->getFactory()->createProductMetricGapTable($idSearchRankingMetric)->fetchData(),
        );
    }

    /**
     * `null` (no `?metric=` in the request) is the real default view — gaps across every active metric,
     * not a fallback to avoid an empty-looking page. A real, positive metric ID narrows to just that one.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return int|null
     */
    protected function resolveIdSearchRankingMetric(Request $request): ?int
    {
        $requestedId = $request->query->getInt(static::PARAM_METRIC, 0);

        return $requestedId > 0 ? $requestedId : null;
    }
}
