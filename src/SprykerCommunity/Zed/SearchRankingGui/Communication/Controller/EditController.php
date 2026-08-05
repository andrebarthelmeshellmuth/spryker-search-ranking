<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricTable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class EditController extends AbstractController
{
    /**
     * @var string
     */
    protected const URL_METRIC_LIST = '/search-ranking-gui';

    /**
     * @var string
     */
    protected const PARAM_ID_SEARCH_RANKING_METRIC = 'idSearchRankingMetric';

    /**
     * @var string
     */
    protected const PARAM_FORMULA = 'formula';

    /**
     * @var string
     */
    protected const PARAM_IS_HIGHER_BETTER = 'isHigherBetter';

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
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function indexAction(Request $request)
    {
        $idSearchRankingMetric = $this->castId(
            $request->query->get(MetricTable::URL_PARAM_ID_SEARCH_RANKING_METRIC),
        );
        $storeName = $this->resolveStoreName($request);
        $localeName = $this->resolveLocaleName($request);
        $redirectUrl = sprintf('%s?%s=%s&%s=%s', static::URL_METRIC_LIST, static::PARAM_STORE_NAME, $storeName, static::PARAM_LOCALE_NAME, $localeName);

        $dataProvider = $this->getFactory()->createMetricFormDataProvider();
        $metricTransfer = $dataProvider->getData($idSearchRankingMetric, $storeName, $localeName);

        if ($metricTransfer === null) {
            $this->addErrorMessage(sprintf('Metric with id %d does not exist.', $idSearchRankingMetric));

            return $this->redirectResponse($redirectUrl);
        }

        $metricForm = $this->getFactory()
            ->getMetricForm($metricTransfer, $dataProvider->getOptions())
            ->handleRequest($request);

        if ($metricForm->isSubmitted() && $metricForm->isValid()) {
            $submittedMetricTransfer = $metricForm->getData();
            $savedMetricTransfer = $this->getFactory()->getSearchRankingFacade()->saveMetric($submittedMetricTransfer);

            if ($submittedMetricTransfer->getWeight() !== null) {
                $this->getFactory()->getSearchRankingFacade()->saveMetricWeight(
                    $savedMetricTransfer->getIdSearchRankingMetricOrFail(),
                    $storeName,
                    $localeName,
                    $submittedMetricTransfer->getWeight(),
                );
            }

            $this->addSuccessMessage(sprintf('Metric "%s" was updated.', $metricTransfer->getName()));

            return $this->redirectResponse($redirectUrl);
        }

        return $this->viewResponse([
            'metricForm' => $metricForm->createView(),
            'metricName' => $metricTransfer->getName(),
            'idSearchRankingMetric' => $metricTransfer->getIdSearchRankingMetric(),
            'storeName' => $storeName,
            'localeName' => $localeName,
            'formAction' => sprintf('/search-ranking-gui/edit?%s=%d&%s=%s&%s=%s', MetricTable::URL_PARAM_ID_SEARCH_RANKING_METRIC, $idSearchRankingMetric, static::PARAM_STORE_NAME, $storeName, static::PARAM_LOCALE_NAME, $localeName),
        ]);
    }

    /**
     * Read-only preview endpoint backing the live formula/curve-fit plot on the edit page — side-effect
     * free, so a GET is correct even though the browser-typed formula may not be saved (or even valid).
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function previewAction(Request $request): JsonResponse
    {
        $idSearchRankingMetric = $this->castId($request->query->get(static::PARAM_ID_SEARCH_RANKING_METRIC));
        $formula = (string)$request->query->get(static::PARAM_FORMULA, '');
        $isHigherBetter = (bool)$request->query->get(static::PARAM_IS_HIGHER_BETTER, true);

        $previewTransfer = $this->getFactory()
            ->getSearchRankingFacade()
            ->previewFormula($idSearchRankingMetric, $formula, $isHigherBetter, $this->resolveStoreName($request), $this->resolveLocaleName($request));

        return $this->jsonResponse($previewTransfer->toArray(true, true));
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
