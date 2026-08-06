<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
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
     * @var string
     */
    protected const URL_SETTINGS = '/search-ranking-gui/settings';

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
        $searchRankingFacade = $this->getFactory()->getSearchRankingFacade();

        $settingsForm = $this->getFactory()
            ->getSettingsForm([
                SettingsForm::FIELD_RELEVANCE_WEIGHT => $searchRankingFacade->getRelevanceWeight($storeName, $localeName),
                SettingsForm::FIELD_RELEVANCE_SATURATION_POINT => $searchRankingFacade->getRelevanceSaturationPoint($storeName, $localeName),
                SettingsForm::FIELD_SPECIFICITY_BLEND_WEIGHT => $searchRankingFacade->getSpecificityBlendWeight($storeName, $localeName),
                SettingsForm::FIELD_SPECIFICITY_SATURATION_POINT => $searchRankingFacade->getSpecificitySaturationPoint($storeName, $localeName),
                SettingsForm::FIELD_SPECIFICITY_CURVE_EXPONENT => $searchRankingFacade->getSpecificityCurveExponent($storeName, $localeName),
                SettingsForm::FIELD_SPECIFICITY_WEIGHT_EXPONENT => $searchRankingFacade->getSpecificityWeightExponent($storeName, $localeName),
                SettingsForm::FIELD_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE => $searchRankingFacade->getSpecificityWeightShiftMagnitude($storeName, $localeName),
            ])
            ->handleRequest($request);

        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            $settingsData = $settingsForm->getData();
            $searchRankingFacade->saveRelevanceWeight($storeName, $localeName, (float)$settingsData[SettingsForm::FIELD_RELEVANCE_WEIGHT]);
            $searchRankingFacade->saveRelevanceSaturationPoint($storeName, $localeName, (float)$settingsData[SettingsForm::FIELD_RELEVANCE_SATURATION_POINT]);
            $searchRankingFacade->saveSpecificityBlendWeight($storeName, $localeName, (float)$settingsData[SettingsForm::FIELD_SPECIFICITY_BLEND_WEIGHT]);
            $searchRankingFacade->saveSpecificitySaturationPoint($storeName, $localeName, (float)$settingsData[SettingsForm::FIELD_SPECIFICITY_SATURATION_POINT]);
            $searchRankingFacade->saveSpecificityCurveExponent($storeName, $localeName, (float)$settingsData[SettingsForm::FIELD_SPECIFICITY_CURVE_EXPONENT]);
            $searchRankingFacade->saveSpecificityWeightExponent($storeName, $localeName, (float)$settingsData[SettingsForm::FIELD_SPECIFICITY_WEIGHT_EXPONENT]);
            $searchRankingFacade->saveSpecificityWeightShiftMagnitude($storeName, $localeName, (float)$settingsData[SettingsForm::FIELD_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE]);
            $this->addSuccessMessage('Ranking settings were saved.');

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
            'settingsForm' => $settingsForm->createView(),
            'stores' => $this->getFactory()->getAllStoreNames(),
            'locales' => $this->getFactory()->getAllLocaleNames(),
            'selectedStoreName' => $storeName,
            'selectedLocaleName' => $localeName,
            'formAction' => sprintf('%s?%s=%s&%s=%s', static::URL_SETTINGS, static::PARAM_STORE_NAME, $storeName, static::PARAM_LOCALE_NAME, $localeName),
            'isSpecificityWeightingEnabled' => $searchRankingFacade->isSpecificityWeightingEnabled(),
        ]);
    }
}
