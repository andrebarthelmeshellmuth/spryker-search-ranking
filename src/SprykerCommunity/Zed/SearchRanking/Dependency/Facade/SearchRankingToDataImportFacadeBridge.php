<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Dependency\Facade;

use Generated\Shared\Transfer\DataImportConfigurationTransfer;

class SearchRankingToDataImportFacadeBridge implements SearchRankingToDataImportFacadeInterface
{
    /**
     * @var \Spryker\Zed\DataImport\Business\DataImportFacadeInterface
     */
    protected $dataImportFacade;

    /**
     * @param \Spryker\Zed\DataImport\Business\DataImportFacadeInterface $dataImportFacade
     */
    public function __construct($dataImportFacade)
    {
        $this->dataImportFacade = $dataImportFacade;
    }

    /**
     * @param \Generated\Shared\Transfer\DataImportConfigurationTransfer $dataImportConfigurationTransfer
     *
     * @return array<string>
     */
    public function getImportersDumpByConfiguration(DataImportConfigurationTransfer $dataImportConfigurationTransfer): array
    {
        return $this->dataImportFacade->getImportersDumpByConfiguration($dataImportConfigurationTransfer);
    }
}
