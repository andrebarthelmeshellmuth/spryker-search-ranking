<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use ArrayObject;
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
        $metricTransfers = $this->getFactory()->getSearchRankingFacade()->getActiveMetricCollection()->getMetrics();
        $idSearchRankingMetric = $this->resolveIdSearchRankingMetric($request, $metricTransfers);

        return $this->viewResponse([
            'metrics' => $metricTransfers,
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
        $metricTransfers = $this->getFactory()->getSearchRankingFacade()->getActiveMetricCollection()->getMetrics();
        $idSearchRankingMetric = $this->resolveIdSearchRankingMetric($request, $metricTransfers);

        return $this->jsonResponse(
            $this->getFactory()->createProductMetricGapTable($idSearchRankingMetric)->fetchData(),
        );
    }

    /**
     * Defaults to the first active metric when none was explicitly selected — an empty grid on first
     * visit would look broken, not like "nothing to show yet". `null` only when there is no active
     * metric at all to default to.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \ArrayObject<int, \Generated\Shared\Transfer\SearchRankingMetricTransfer> $metricTransfers
     *
     * @return int|null
     */
    protected function resolveIdSearchRankingMetric(Request $request, ArrayObject $metricTransfers): ?int
    {
        $requestedId = $request->query->getInt(static::PARAM_METRIC, 0);

        if ($requestedId > 0) {
            return $requestedId;
        }

        foreach ($metricTransfers as $metricTransfer) {
            return $metricTransfer->getIdSearchRankingMetricOrFail();
        }

        return null;
    }
}
