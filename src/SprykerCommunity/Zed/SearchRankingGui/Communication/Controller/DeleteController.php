<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricTable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class DeleteController extends AbstractController
{
    /**
     * @var string
     */
    protected const URL_METRIC_LIST = '/search-ranking-gui';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction(Request $request): RedirectResponse
    {
        $deleteForm = $this->getFactory()->createDeleteForm()->handleRequest($request);

        if (!$deleteForm->isSubmitted() || !$deleteForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse(static::URL_METRIC_LIST);
        }

        $idSearchRankingMetric = $this->castId(
            $request->query->get(MetricTable::URL_PARAM_ID_SEARCH_RANKING_METRIC),
        );

        $this->getFactory()->getSearchRankingFacade()->deleteMetric($idSearchRankingMetric);
        $this->getFactory()->getSearchRankingStorageFacade()->publishRankingConfiguration();
        $this->addSuccessMessage('Metric was deleted.');

        return $this->redirectResponse(static::URL_METRIC_LIST);
    }
}
