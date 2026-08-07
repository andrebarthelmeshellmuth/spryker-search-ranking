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
class MetricHistoryController extends AbstractController
{
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
        $storeName = $this->resolveStoreName($request);
        $localeName = $this->resolveLocaleName($request);

        return $this->viewResponse([
            'metricHistoryTable' => $this->getFactory()->createMetricHistoryTable($storeName, $localeName)->render(),
            'stores' => $this->getFactory()->getAllStoreNames(),
            'locales' => $this->getFactory()->getAllLocaleNames(),
            'selectedStoreName' => $storeName,
            'selectedLocaleName' => $localeName,
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function tableAction(Request $request): JsonResponse
    {
        return $this->jsonResponse(
            $this->getFactory()
                ->createMetricHistoryTable($this->resolveStoreName($request), $this->resolveLocaleName($request))
                ->fetchData(),
        );
    }

    /**
     * Null (not a default scope) means "no filter" — this table is a cross-scope audit trail, unlike
     * every other scoped page in this GUI, so an unset/blank query param means "show every store", not
     * "fall back to DE".
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    protected function resolveStoreName(Request $request): ?string
    {
        $storeName = (string)$request->query->get(static::PARAM_STORE_NAME, '');

        return $storeName === '' ? null : $storeName;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    protected function resolveLocaleName(Request $request): ?string
    {
        $localeName = (string)$request->query->get(static::PARAM_LOCALE_NAME, '');

        return $localeName === '' ? null : $localeName;
    }
}
