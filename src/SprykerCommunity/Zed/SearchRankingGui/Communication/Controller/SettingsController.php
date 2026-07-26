<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\SettingsForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class SettingsController extends AbstractController
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
        $searchRankingFacade = $this->getFactory()->getSearchRankingFacade();

        $settingsForm = $this->getFactory()
            ->getSettingsForm([
                SettingsForm::FIELD_RELEVANCE_WEIGHT => $searchRankingFacade->getRelevanceWeight(),
                SettingsForm::FIELD_RELEVANCE_SATURATION_POINT => $searchRankingFacade->getRelevanceSaturationPoint(),
                SettingsForm::FIELD_ENTROPY_PROBE_RESULT_SIZE => $searchRankingFacade->getEntropyProbeResultSize(),
                SettingsForm::FIELD_ENTROPY_WEIGHT_EXPONENT => $searchRankingFacade->getEntropyWeightExponent(),
                SettingsForm::FIELD_ENTROPY_WEIGHT_SHIFT_MAGNITUDE => $searchRankingFacade->getEntropyWeightShiftMagnitude(),
            ])
            ->handleRequest($request);

        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            $settingsData = $settingsForm->getData();
            $searchRankingFacade->saveRelevanceWeight((float)$settingsData[SettingsForm::FIELD_RELEVANCE_WEIGHT]);
            $searchRankingFacade->saveRelevanceSaturationPoint((float)$settingsData[SettingsForm::FIELD_RELEVANCE_SATURATION_POINT]);
            $searchRankingFacade->saveEntropyProbeResultSize((int)$settingsData[SettingsForm::FIELD_ENTROPY_PROBE_RESULT_SIZE]);
            $searchRankingFacade->saveEntropyWeightExponent((float)$settingsData[SettingsForm::FIELD_ENTROPY_WEIGHT_EXPONENT]);
            $searchRankingFacade->saveEntropyWeightShiftMagnitude((float)$settingsData[SettingsForm::FIELD_ENTROPY_WEIGHT_SHIFT_MAGNITUDE]);
            $this->getFactory()->getSearchRankingStorageFacade()->publishRankingConfiguration();
            $this->addSuccessMessage('Ranking settings were saved.');

            return $this->redirectResponse(static::URL_METRIC_LIST);
        }

        return $this->viewResponse([
            'settingsForm' => $settingsForm->createView(),
        ]);
    }
}
