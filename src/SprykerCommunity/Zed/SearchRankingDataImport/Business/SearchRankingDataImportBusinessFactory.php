<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business;

use Spryker\Zed\DataImport\Business\DataImportBusinessFactory;
use Spryker\Zed\DataImport\Business\Model\DataImporterInterface;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingMetric\SearchRankingMetricWriterStep;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\MetricNameToIdSearchRankingMetricStep;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\ProductAbstractSkuToIdProductAbstractStep;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\SearchRankingProductMetricWriterStep;

/**
 * @method \SprykerCommunity\Zed\SearchRankingDataImport\SearchRankingDataImportConfig getConfig()
 * @method \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetStepBrokerTransactionAware createTransactionAwareDataSetStepBroker($bulkSize = null)
 * @method \Spryker\Zed\DataImport\Business\Model\DataImporter getCsvDataImporterFromConfig(\Generated\Shared\Transfer\DataImporterConfigurationTransfer $dataImporterConfigurationTransfer)
 */
class SearchRankingDataImportBusinessFactory extends DataImportBusinessFactory
{
    /**
     * @return \Spryker\Zed\DataImport\Business\Model\DataImporterInterface
     */
    public function getSearchRankingMetricDataImporter(): DataImporterInterface
    {
        $dataImporter = $this->getCsvDataImporterFromConfig(
            $this->getConfig()->getSearchRankingMetricDataImporterConfiguration(),
        );

        $dataSetStepBroker = $this->createTransactionAwareDataSetStepBroker();
        $dataSetStepBroker->addStep($this->createSearchRankingMetricWriterStep());

        $dataImporter->addDataSetStepBroker($dataSetStepBroker);

        return $dataImporter;
    }

    /**
     * @return \Spryker\Zed\DataImport\Business\Model\DataImporterInterface
     */
    public function getSearchRankingProductMetricDataImporter(): DataImporterInterface
    {
        $dataImporter = $this->getCsvDataImporterFromConfig(
            $this->getConfig()->getSearchRankingProductMetricDataImporterConfiguration(),
        );

        $dataSetStepBroker = $this->createTransactionAwareDataSetStepBroker();
        $dataSetStepBroker
            ->addStep($this->createMetricNameToIdSearchRankingMetricStep())
            ->addStep($this->createProductAbstractSkuToIdProductAbstractStep())
            ->addStep($this->createSearchRankingProductMetricWriterStep());

        $dataImporter->addDataSetStepBroker($dataSetStepBroker);

        return $dataImporter;
    }

    /**
     * @return \Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface
     */
    public function createSearchRankingMetricWriterStep(): DataImportStepInterface
    {
        return new SearchRankingMetricWriterStep();
    }

    /**
     * @return \Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface
     */
    public function createMetricNameToIdSearchRankingMetricStep(): DataImportStepInterface
    {
        return new MetricNameToIdSearchRankingMetricStep();
    }

    /**
     * @return \Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface
     */
    public function createProductAbstractSkuToIdProductAbstractStep(): DataImportStepInterface
    {
        return new ProductAbstractSkuToIdProductAbstractStep();
    }

    /**
     * @return \Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface
     */
    public function createSearchRankingProductMetricWriterStep(): DataImportStepInterface
    {
        return new SearchRankingProductMetricWriterStep();
    }
}
