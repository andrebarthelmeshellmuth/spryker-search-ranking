<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
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
     * @var string
     */
    protected const PARAM_STORE_NAME = 'storeName';

    /**
     * @var string
     */
    protected const PARAM_LOCALE_NAME = 'localeName';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array<string, mixed>
     */
    public function indexAction(Request $request): array
    {
        $idSearchRankingMetric = $this->resolveIdSearchRankingMetric($request);
        $storeName = $this->resolveStoreName($request);
        $localeName = $this->resolveLocaleName($request);

        return $this->viewResponse([
            'metrics' => $this->getFactory()->getSearchRankingFacade()->getActiveMetricCollection($storeName, $localeName)->getMetrics(),
            'selectedIdSearchRankingMetric' => $idSearchRankingMetric,
            'productMetricGapTable' => $this->getFactory()->createProductMetricGapTable($idSearchRankingMetric, $storeName, $localeName)->render(),
            'stores' => $this->getFactory()->getAllStoreNames(),
            'locales' => $this->getFactory()->getAllLocaleNames(),
            'storeName' => $storeName,
            'localeName' => $localeName,
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function tableAction(Request $request): JsonResponse
    {
        $idSearchRankingMetric = $this->resolveIdSearchRankingMetric($request);

        return $this->jsonResponse(
            $this->getFactory()
                ->createProductMetricGapTable($idSearchRankingMetric, $this->resolveStoreName($request), $this->resolveLocaleName($request))
                ->fetchData(),
        );
    }

    /**
     * `null` (no `?metric=` in the request) is the real default view — gaps across every active metric,
     * not a fallback to avoid an empty-looking page. A real, positive metric ID narrows to just that one.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    protected function resolveIdSearchRankingMetric(Request $request): ?int
    {
        $requestedId = $request->query->getInt(static::PARAM_METRIC, 0);

        return $requestedId > 0 ? $requestedId : null;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    protected function resolveStoreName(Request $request): string
    {
        return (string)$request->query->get(static::PARAM_STORE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    protected function resolveLocaleName(Request $request): string
    {
        return (string)$request->query->get(static::PARAM_LOCALE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME;
    }
}
