<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Communication\Plugin\DataImport;

use Generated\Shared\Transfer\DataImporterConfigurationTransfer;
use Generated\Shared\Transfer\DataImporterReportTransfer;
use Spryker\Zed\DataImport\Dependency\Plugin\DataImportPluginInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use SprykerCommunity\Zed\SearchRankingDataImport\SearchRankingDataImportConfig;

/**
 * @method \SprykerCommunity\Zed\SearchRankingDataImport\SearchRankingDataImportConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRankingDataImport\Business\SearchRankingDataImportFacadeInterface getFacade()
 */
class SearchRankingMetricDataImportPlugin extends AbstractPlugin implements DataImportPluginInterface
{
    /**
     * {@inheritDoc}
     * - Imports search ranking metric definitions.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\DataImporterConfigurationTransfer|null $dataImporterConfigurationTransfer
     */
    public function import(?DataImporterConfigurationTransfer $dataImporterConfigurationTransfer = null): DataImporterReportTransfer
    {
        return $this->getFacade()->importMetrics($dataImporterConfigurationTransfer);
    }

    /**
     * {@inheritDoc}
     * - Returns the name of the search ranking metric import type.
     *
     * @api
     */
    public function getImportType(): string
    {
        return SearchRankingDataImportConfig::IMPORT_TYPE_SEARCH_RANKING_METRIC;
    }
}
