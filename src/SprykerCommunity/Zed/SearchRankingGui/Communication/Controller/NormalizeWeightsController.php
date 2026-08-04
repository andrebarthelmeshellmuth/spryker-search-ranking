<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
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
     * @var string
     */
    protected const PARAM_STORE_NAME = 'storeName';

    /**
     * @var string
     */
    protected const PARAM_LOCALE_NAME = 'localeName';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function indexAction(Request $request): RedirectResponse
    {
        $storeName = (string)$request->query->get(static::PARAM_STORE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME;
        $localeName = (string)$request->query->get(static::PARAM_LOCALE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME;
        $redirectUrl = sprintf('%s?%s=%s&%s=%s', static::URL_METRIC_LIST, static::PARAM_STORE_NAME, $storeName, static::PARAM_LOCALE_NAME, $localeName);

        $normalizeForm = $this->getFactory()->createNormalizeWeightsForm()->handleRequest($request);

        if (!$normalizeForm->isSubmitted() || !$normalizeForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse($redirectUrl);
        }

        $wereWeightsChanged = $this->getFactory()->getSearchRankingFacade()->normalizeActiveMetricWeights($storeName, $localeName);

        if ($wereWeightsChanged) {
            $this->getFactory()->getSearchRankingStorageFacade()->publishRankingConfiguration();
            $this->addSuccessMessage('Active metric weights were normalized to sum to 1.');
        } else {
            $this->addSuccessMessage('Active metric weights already sum to 1 — nothing to normalize.');
        }

        return $this->redirectResponse($redirectUrl);
    }
}
