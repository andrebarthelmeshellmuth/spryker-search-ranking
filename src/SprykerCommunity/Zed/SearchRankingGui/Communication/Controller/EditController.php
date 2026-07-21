<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
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
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function indexAction(Request $request)
    {
        $idSearchRankingMetric = $this->castId(
            $request->query->get(MetricTable::URL_PARAM_ID_SEARCH_RANKING_METRIC),
        );

        $dataProvider = $this->getFactory()->createMetricFormDataProvider();
        $metricTransfer = $dataProvider->getData($idSearchRankingMetric);

        if ($metricTransfer === null) {
            $this->addErrorMessage(sprintf('Metric with id %d does not exist.', $idSearchRankingMetric));

            return $this->redirectResponse(static::URL_METRIC_LIST);
        }

        $metricForm = $this->getFactory()
            ->getMetricForm($metricTransfer, $dataProvider->getOptions())
            ->handleRequest($request);

        if ($metricForm->isSubmitted() && $metricForm->isValid()) {
            $this->getFactory()->getSearchRankingFacade()->saveMetric($metricForm->getData());
            $this->getFactory()->getSearchRankingStorageFacade()->publishRankingConfiguration();
            $this->addSuccessMessage(sprintf('Metric "%s" was updated.', $metricTransfer->getName()));

            return $this->redirectResponse(static::URL_METRIC_LIST);
        }

        return $this->viewResponse([
            'metricForm' => $metricForm->createView(),
            'metricName' => $metricTransfer->getName(),
            'idSearchRankingMetric' => $metricTransfer->getIdSearchRankingMetric(),
        ]);
    }

    /**
     * Read-only preview endpoint backing the live formula/curve-fit plot on the edit page — side-effect
     * free, so a GET is correct even though the browser-typed formula may not be saved (or even valid).
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function previewAction(Request $request): JsonResponse
    {
        $idSearchRankingMetric = $this->castId($request->query->get(static::PARAM_ID_SEARCH_RANKING_METRIC));
        $formula = (string)$request->query->get(static::PARAM_FORMULA, '');
        $isHigherBetter = (bool)$request->query->get(static::PARAM_IS_HIGHER_BETTER, true);

        $previewTransfer = $this->getFactory()
            ->getSearchRankingFacade()
            ->previewFormula($idSearchRankingMetric, $formula, $isHigherBetter);

        return $this->jsonResponse($previewTransfer->toArray(true, true));
    }
}
