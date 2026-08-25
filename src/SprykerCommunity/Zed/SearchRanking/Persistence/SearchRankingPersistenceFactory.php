<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Persistence;

use Orm\Zed\SearchRanking\Persistence\SpySearchRankingEmbeddingQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricDigestQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricHistoryQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricStoreConfigQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingMetricWeightQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingProductMetricQuery;
use Orm\Zed\SearchRanking\Persistence\SpySearchRankingScopeCopyLockQuery;
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
    public function createSearchRankingMetricQuery(): SpySearchRankingMetricQuery
    {
        return SpySearchRankingMetricQuery::create();
    }

    public function createSearchRankingProductMetricQuery(): SpySearchRankingProductMetricQuery
    {
        return SpySearchRankingProductMetricQuery::create();
    }

    public function createSearchRankingSettingQuery(): SpySearchRankingSettingQuery
    {
        return SpySearchRankingSettingQuery::create();
    }

    public function createSearchRankingMetricWeightQuery(): SpySearchRankingMetricWeightQuery
    {
        return SpySearchRankingMetricWeightQuery::create();
    }

    public function createSearchRankingMetricStoreConfigQuery(): SpySearchRankingMetricStoreConfigQuery
    {
        return SpySearchRankingMetricStoreConfigQuery::create();
    }

    public function createSearchRankingMapper(): SearchRankingMapper
    {
        return new SearchRankingMapper();
    }

    public function createSearchRankingMetricDigestQuery(): SpySearchRankingMetricDigestQuery
    {
        return SpySearchRankingMetricDigestQuery::create();
    }

    public function createSearchRankingMetricHistoryQuery(): SpySearchRankingMetricHistoryQuery
    {
        return SpySearchRankingMetricHistoryQuery::create();
    }

    public function createSearchRankingScopeCopyLockQuery(): SpySearchRankingScopeCopyLockQuery
    {
        return SpySearchRankingScopeCopyLockQuery::create();
    }

    public function createSearchRankingEmbeddingQuery(): SpySearchRankingEmbeddingQuery
    {
        return SpySearchRankingEmbeddingQuery::create();
    }
}
