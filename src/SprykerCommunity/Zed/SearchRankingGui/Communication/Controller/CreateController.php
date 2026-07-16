<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class CreateController extends AbstractController
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
        $dataProvider = $this->getFactory()->createMetricFormDataProvider();
        $metricForm = $this->getFactory()
            ->getMetricForm($dataProvider->getData(), $dataProvider->getOptions())
            ->handleRequest($request);

        if ($metricForm->isSubmitted() && $metricForm->isValid()) {
            $metricTransfer = $metricForm->getData();
            $this->getFactory()->getSearchRankingFacade()->saveMetric($metricTransfer);
            $this->getFactory()->getSearchRankingStorageFacade()->publishRankingConfiguration();
            $this->addSuccessMessage(sprintf('Metric "%s" was created.', $metricTransfer->getName()));

            return $this->redirectResponse(static::URL_METRIC_LIST);
        }

        return $this->viewResponse([
            'metricForm' => $metricForm->createView(),
        ]);
    }
}
