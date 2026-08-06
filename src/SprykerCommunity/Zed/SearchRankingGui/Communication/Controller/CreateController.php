<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
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
        $storeName = (string)$request->query->get(static::PARAM_STORE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME;
        $localeName = (string)$request->query->get(static::PARAM_LOCALE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME;

        $dataProvider = $this->getFactory()->createMetricFormDataProvider();
        $metricForm = $this->getFactory()
            ->getMetricForm($dataProvider->getData(null, $storeName, $localeName), $dataProvider->getOptions())
            ->handleRequest($request);

        if ($metricForm->isSubmitted() && $metricForm->isValid()) {
            $metricTransfer = $metricForm->getData();
            $savedMetricTransfer = $this->getFactory()->getSearchRankingFacade()->saveMetric($metricTransfer, $storeName, $localeName);

            if ($metricTransfer->getWeight() !== null) {
                $this->getFactory()->getSearchRankingFacade()->saveMetricWeight(
                    $savedMetricTransfer->getIdSearchRankingMetricOrFail(),
                    $storeName,
                    $localeName,
                    $metricTransfer->getWeight(),
                );
            }

            $this->addSuccessMessage(sprintf('Metric "%s" was created.', $metricTransfer->getName()));

            return $this->redirectResponse(sprintf(
                '%s?%s=%s&%s=%s',
                static::URL_METRIC_LIST,
                static::PARAM_STORE_NAME,
                $storeName,
                static::PARAM_LOCALE_NAME,
                $localeName,
            ));
        }

        return $this->viewResponse([
            'metricForm' => $metricForm->createView(),
            'formAction' => sprintf('/search-ranking-gui/create?%s=%s&%s=%s', static::PARAM_STORE_NAME, $storeName, static::PARAM_LOCALE_NAME, $localeName),
        ]);
    }
}
