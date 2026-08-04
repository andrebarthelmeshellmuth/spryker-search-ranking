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
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\DataProvider\MetricFormDataProvider;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\MetricForm;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\NormalizeWeightsForm;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\SettingsForm;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricHistoryTable;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricTable;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\ProductMetricGapTable;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Table\ProductMetricTable;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToLocaleFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingStorageFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapFinder;
use SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapFinderInterface;
use SprykerCommunity\Zed\SearchRankingGui\SearchRankingGuiDependencyProvider;
use Symfony\Component\Form\FormInterface;

class SearchRankingGuiCommunicationFactory extends AbstractCommunicationFactory
{
    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricTable
     */
    public function createMetricTable(string $storeName, string $localeName): MetricTable
    {
        return new MetricTable($this->getSearchRankingMetricPropelQuery(), $storeName, $localeName);
    }

    /**
     * @param string $storeName
     * @param string $localeName
     *
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Table\ProductMetricTable
     */
    public function createProductMetricTable(string $storeName, string $localeName): ProductMetricTable
    {
        return new ProductMetricTable($this->getSearchRankingProductMetricPropelQuery(), $storeName, $localeName);
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Table\MetricHistoryTable
     */
    public function createMetricHistoryTable(): MetricHistoryTable
    {
        return new MetricHistoryTable($this->getSearchRankingMetricHistoryPropelQuery());
    }

    /**
     * @param int|null $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Table\ProductMetricGapTable
     */
    public function createProductMetricGapTable(?int $idSearchRankingMetric, string $storeName, string $localeName): ProductMetricGapTable
    {
        return new ProductMetricGapTable(
            $this->createProductMetricGapFinder(),
            $idSearchRankingMetric,
            $storeName,
            $localeName,
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapFinderInterface
     */
    public function createProductMetricGapFinder(): ProductMetricGapFinderInterface
    {
        return new ProductMetricGapFinder();
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
     * Every store name, for the Store+Locale scope selector every scoped page in this module shows.
     *
     * @return array<string>
     */
    public function getAllStoreNames(): array
    {
        return array_map(
            static fn ($storeTransfer) => $storeTransfer->getNameOrFail(),
            $this->getStoreFacade()->getAllStores(),
        );
    }

    /**
     * Every locale available in this shop, for the same scope selector — deliberately NOT filtered to the
     * selected store's own locales (matches spryker-community/search-ranking-optimizer's own
     * OptimizeRunForm/CalibrationUploadForm selector, which lists every locale independent of store too).
     *
     * @return array<string>
     */
    public function getAllLocaleNames(): array
    {
        return array_values($this->getLocaleFacade()->getAvailableLocales());
    }
}
