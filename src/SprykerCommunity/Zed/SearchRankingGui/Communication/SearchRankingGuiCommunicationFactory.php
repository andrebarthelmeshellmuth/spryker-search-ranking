<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Spryker\Zed\Gui\Communication\Form\DeleteForm;
use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\CalibrationApplyForm;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\CalibrationUploadForm;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\DataProvider\MetricFormDataProvider;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\MetricForm;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\NormalizeWeightsForm;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\SettingsForm;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricHistoryTable;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricTable;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\ProductMetricTable;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToLocaleFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingStorageFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\SearchRankingGuiDependencyProvider;
use Symfony\Component\Form\FormInterface;

class SearchRankingGuiCommunicationFactory extends AbstractCommunicationFactory
{
    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricTable
     */
    public function createMetricTable(): MetricTable
    {
        return new MetricTable($this->getSearchRankingMetricPropelQuery());
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Table\ProductMetricTable
     */
    public function createProductMetricTable(): ProductMetricTable
    {
        return new ProductMetricTable($this->getSearchRankingProductMetricPropelQuery());
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricHistoryTable
     */
    public function createMetricHistoryTable(): MetricHistoryTable
    {
        return new MetricHistoryTable($this->getSearchRankingMetricHistoryPropelQuery());
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer|null $metricTransfer
     * @param array<string, mixed> $options
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    public function getMetricForm(?SearchRankingMetricTransfer $metricTransfer, array $options): FormInterface
    {
        return $this->getFormFactory()->create(MetricForm::class, $metricTransfer, $options);
    }

    /**
     * @return \Symfony\Component\Form\FormInterface
     */
    public function createDeleteForm(): FormInterface
    {
        return $this->getFormFactory()->create(DeleteForm::class, [], ['fields' => []]);
    }

    /**
     * @return \Symfony\Component\Form\FormInterface
     */
    public function createNormalizeWeightsForm(): FormInterface
    {
        return $this->getFormFactory()->create(NormalizeWeightsForm::class);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Form\DataProvider\MetricFormDataProvider
     */
    public function createMetricFormDataProvider(): MetricFormDataProvider
    {
        return new MetricFormDataProvider($this->getSearchRankingFacade());
    }

    /**
     * @param array<string, mixed> $settingsData
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    public function getSettingsForm(array $settingsData): FormInterface
    {
        return $this->getFormFactory()->create(SettingsForm::class, $settingsData);
    }

    /**
     * @return \Symfony\Component\Form\FormInterface
     */
    public function createCalibrationUploadForm(): FormInterface
    {
        $storeChoices = [];
        foreach ($this->getStoreFacade()->getAllStores() as $storeTransfer) {
            $storeChoices[$storeTransfer->getNameOrFail()] = $storeTransfer->getNameOrFail();
        }

        $localeChoices = [];
        foreach ($this->getLocaleFacade()->getAvailableLocales() as $localeName) {
            $localeChoices[$localeName] = $localeName;
        }

        return $this->getFormFactory()->create(CalibrationUploadForm::class, null, [
            CalibrationUploadForm::OPTION_STORE_CHOICES => $storeChoices,
            CalibrationUploadForm::OPTION_LOCALE_CHOICES => $localeChoices,
        ]);
    }

    /**
     * @param float $relevanceSaturationPoint
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    public function createCalibrationApplyForm(float $relevanceSaturationPoint): FormInterface
    {
        return $this->getFormFactory()->create(CalibrationApplyForm::class, [
            CalibrationApplyForm::FIELD_RELEVANCE_SATURATION_POINT => $relevanceSaturationPoint,
        ]);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface
     */
    public function getSearchRankingFacade(): SearchRankingGuiToSearchRankingFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingGuiDependencyProvider::FACADE_SEARCH_RANKING);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingStorageFacadeInterface
     */
    public function getSearchRankingStorageFacade(): SearchRankingGuiToSearchRankingStorageFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingGuiDependencyProvider::FACADE_SEARCH_RANKING_STORAGE);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToStoreFacadeInterface
     */
    public function getStoreFacade(): SearchRankingGuiToStoreFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingGuiDependencyProvider::FACADE_STORE);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToLocaleFacadeInterface
     */
    public function getLocaleFacade(): SearchRankingGuiToLocaleFacadeInterface
    {
        return $this->getProvidedDependency(SearchRankingGuiDependencyProvider::FACADE_LOCALE);
    }

    /**
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery
     */
    public function getSearchRankingMetricPropelQuery(): SpySearchRankingMetricQuery
    {
        return $this->getProvidedDependency(SearchRankingGuiDependencyProvider::PROPEL_QUERY_SEARCH_RANKING_METRIC);
    }

    /**
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery
     */
    public function getSearchRankingProductMetricPropelQuery(): SpySearchRankingProductMetricQuery
    {
        return $this->getProvidedDependency(SearchRankingGuiDependencyProvider::PROPEL_QUERY_SEARCH_RANKING_PRODUCT_METRIC);
    }

    /**
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery
     */
    public function getSearchRankingMetricHistoryPropelQuery(): SpySearchRankingMetricHistoryQuery
    {
        return $this->getProvidedDependency(SearchRankingGuiDependencyProvider::PROPEL_QUERY_SEARCH_RANKING_METRIC_HISTORY);
    }
}
