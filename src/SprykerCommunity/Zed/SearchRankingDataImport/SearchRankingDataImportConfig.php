<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport;

use Generated\Shared\Transfer\DataImporterConfigurationTransfer;
use Spryker\Zed\DataImport\DataImportConfig;

class SearchRankingDataImportConfig extends DataImportConfig
{
    /**
     * @var string
     */
    public const IMPORT_TYPE_SEARCH_RANKING_METRIC = 'search-ranking-metric';

    /**
     * @var string
     */
    public const IMPORT_TYPE_SEARCH_RANKING_PRODUCT_METRIC = 'search-ranking-product-metric';

    /**
     * @var int
     */
    final protected const MODULE_ROOT_DIRECTORY_LEVEL = 4;

    /**
     * @api
     *
     * @return \Generated\Shared\Transfer\DataImporterConfigurationTransfer
     */
    public function getSearchRankingMetricDataImporterConfiguration(): DataImporterConfigurationTransfer
    {
        return $this->buildImporterConfiguration(
            $this->getModuleDataImportDirectoryPath() . 'search_ranking_metric.csv',
            static::IMPORT_TYPE_SEARCH_RANKING_METRIC,
        );
    }

    /**
     * @api
     *
     * @return \Generated\Shared\Transfer\DataImporterConfigurationTransfer
     */
    public function getSearchRankingProductMetricDataImporterConfiguration(): DataImporterConfigurationTransfer
    {
        return $this->buildImporterConfiguration(
            $this->getModuleDataImportDirectoryPath() . 'search_ranking_product_metric.csv',
            static::IMPORT_TYPE_SEARCH_RANKING_PRODUCT_METRIC,
        );
    }

    /**
     * @return string
     */
    protected function getModuleDataImportDirectoryPath(): string
    {
        return $this->getModuleRoot() . 'data' . DIRECTORY_SEPARATOR . 'import' . DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    protected function getModuleRoot(): string
    {
        /** @var positive-int $directoryLevel */
        $directoryLevel = static::MODULE_ROOT_DIRECTORY_LEVEL;

        return realpath(
            dirname(__DIR__, $directoryLevel),
        ) . DIRECTORY_SEPARATOR;
    }
}
