<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingSettingQuery;
use Spryker\Zed\Kernel\Persistence\AbstractPersistenceFactory;
use SprykerCommunity\Zed\SearchRanking\Persistence\Propel\Mapper\SearchRankingMapper;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface getRepository()
 * @method \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface getEntityManager()
 */
class SearchRankingPersistenceFactory extends AbstractPersistenceFactory
{
    /**
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery
     */
    public function createSearchRankingMetricQuery(): SpySearchRankingMetricQuery
    {
        return SpySearchRankingMetricQuery::create();
    }

    /**
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery
     */
    public function createSearchRankingProductMetricQuery(): SpySearchRankingProductMetricQuery
    {
        return SpySearchRankingProductMetricQuery::create();
    }

    /**
     * @return \Orm\Zed\SearchRanking\Persistence\SpySearchRankingSettingQuery
     */
    public function createSearchRankingSettingQuery(): SpySearchRankingSettingQuery
    {
        return SpySearchRankingSettingQuery::create();
    }

    /**
     * @return \SprykerCommunity\Zed\SearchRanking\Persistence\Propel\Mapper\SearchRankingMapper
     */
    public function createSearchRankingMapper(): SearchRankingMapper
    {
        return new SearchRankingMapper();
    }
}
