<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetric;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingPersistenceFactory getFactory()
 */
class SearchRankingEntityManager extends AbstractEntityManager implements SearchRankingEntityManagerInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     *
     * @return \Generated\Shared\Transfer\SearchRankingMetricTransfer
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer): SearchRankingMetricTransfer
    {
        $metricEntity = null;

        if ($metricTransfer->getIdSearchRankingMetric() !== null) {
            $metricEntity = $this->getFactory()
                ->createSearchRankingMetricQuery()
                ->findOneByIdSearchRankingMetric($metricTransfer->getIdSearchRankingMetric());
        }

        if ($metricEntity === null) {
            $metricEntity = new SpySearchRankingMetric();
        }

        $mapper = $this->getFactory()->createSearchRankingMapper();
        $metricEntity = $mapper->mapMetricTransferToEntity($metricTransfer, $metricEntity);
        $metricEntity->save();

        return $mapper->mapMetricEntityToTransfer($metricEntity, $metricTransfer);
    }

    /**
     * @param int $idSearchRankingMetric
     *
     * @return void
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $metricEntity = $this->getFactory()
            ->createSearchRankingMetricQuery()
            ->findOneByIdSearchRankingMetric($idSearchRankingMetric);

        if ($metricEntity === null) {
            return;
        }

        $metricEntity->delete();
    }

    /**
     * @param array<int, float> $normalizedValuesByIdProductMetric
     *
     * @return void
     */
    public function updateNormalizedValues(array $normalizedValuesByIdProductMetric): void
    {
        if ($normalizedValuesByIdProductMetric === []) {
            return;
        }

        $productMetricEntities = $this->getFactory()
            ->createSearchRankingProductMetricQuery()
            ->filterByIdSearchRankingProductMetric_In(array_keys($normalizedValuesByIdProductMetric))
            ->find();

        foreach ($productMetricEntities as $productMetricEntity) {
            $productMetricEntity->setNormalizedValue(
                $normalizedValuesByIdProductMetric[$productMetricEntity->getIdSearchRankingProductMetric()],
            );
            $productMetricEntity->save();
        }
    }

    /**
     * @param string $settingKey
     * @param string $settingValue
     *
     * @return void
     */
    public function saveSetting(string $settingKey, string $settingValue): void
    {
        $settingEntity = $this->getFactory()
            ->createSearchRankingSettingQuery()
            ->filterBySettingKey($settingKey)
            ->findOneOrCreate();

        $settingEntity->setSettingValue($settingValue);
        $settingEntity->save();
    }
}
