<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;
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
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingStorageFacadeInterface;
use SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapQueryBuilder;
use SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapQueryBuilderInterface;
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
     * @param int|null $idSearchRankingMetric
     *
     * @return \SprykerCommunity\Zed\SearchRankingGui\Communication\Table\ProductMetricGapTable
     */
    public function createProductMetricGapTable(?int $idSearchRankingMetric): ProductMetricGapTable
    {
        return new ProductMetricGapTable(
            $this->getProductAbstractPropelQuery(),
            $this->createProductMetricGapQueryBuilder(),
            $idSearchRankingMetric,
        );
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRankingGui\Persistence\ProductMetricGapQueryBuilderInterface
     */
    public function createProductMetricGapQueryBuilder(): ProductMetricGapQueryBuilderInterface
    {
        return new ProductMetricGapQueryBuilder();
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
     * @return \Orm\Zed\Product\Persistence\SpyProductAbstractQuery
     */
    public function getProductAbstractPropelQuery(): SpyProductAbstractQuery
    {
        return $this->getProvidedDependency(SearchRankingGuiDependencyProvider::PROPEL_QUERY_PRODUCT_ABSTRACT);
    }
}
