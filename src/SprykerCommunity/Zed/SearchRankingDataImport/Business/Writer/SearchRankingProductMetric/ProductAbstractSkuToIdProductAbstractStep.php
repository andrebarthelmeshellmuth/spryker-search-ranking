<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric;

use Orm\Zed\Product\Persistence\Map\SpyProductAbstractTableMap;
use Orm\Zed\Product\Persistence\SpyProductAbstractQuery;
use Spryker\Zed\DataImport\Business\Exception\EntityNotFoundException;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use SprykerCommunity\Zed\SearchRankingDataImport\Business\Writer\SearchRankingProductMetric\DataSet\SearchRankingProductMetricDataSetInterface;

class ProductAbstractSkuToIdProductAbstractStep implements DataImportStepInterface
{
    /**
     * @var array<string, int>
     */
    protected array $idProductAbstractCache = [];

    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @throws \Spryker\Zed\DataImport\Business\Exception\EntityNotFoundException
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $abstractSku = (string)$dataSet[SearchRankingProductMetricDataSetInterface::COL_ABSTRACT_SKU];

        if (!isset($this->idProductAbstractCache[$abstractSku])) {
            /** @var int|null $idProductAbstract */
            $idProductAbstract = SpyProductAbstractQuery::create()
                ->filterBySku($abstractSku)
                ->select([SpyProductAbstractTableMap::COL_ID_PRODUCT_ABSTRACT])
                ->findOne();

            if ($idProductAbstract === null) {
                throw new EntityNotFoundException(
                    sprintf('Product abstract with SKU "%s" not found.', $abstractSku),
                );
            }

            $this->idProductAbstractCache[$abstractSku] = (int)$idProductAbstract;
        }

        $dataSet[SearchRankingProductMetricDataSetInterface::KEY_ID_PRODUCT_ABSTRACT] = $this->idProductAbstractCache[$abstractSku];
    }
}
