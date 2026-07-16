<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricTable;
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
            $this->addSuccessMessage(sprintf('Metric "%s" was updated.', $metricTransfer->getName()));

            return $this->redirectResponse(static::URL_METRIC_LIST);
        }

        return $this->viewResponse([
            'metricForm' => $metricForm->createView(),
            'metricName' => $metricTransfer->getName(),
        ]);
    }
}
