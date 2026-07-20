<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class NormalizeWeightsController extends AbstractController
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
        $normalizeForm = $this->getFactory()->createNormalizeWeightsForm()->handleRequest($request);

        if (!$normalizeForm->isSubmitted() || !$normalizeForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse(static::URL_METRIC_LIST);
        }

        $wereWeightsChanged = $this->getFactory()->getSearchRankingFacade()->normalizeActiveMetricWeights();

        if ($wereWeightsChanged) {
            $this->getFactory()->getSearchRankingStorageFacade()->publishRankingConfiguration();
            $this->addSuccessMessage('Active metric weights were normalized to sum to 1.');
        } else {
            $this->addSuccessMessage('Active metric weights already sum to 1 — nothing to normalize.');
        }

        return $this->redirectResponse(static::URL_METRIC_LIST);
    }
}
