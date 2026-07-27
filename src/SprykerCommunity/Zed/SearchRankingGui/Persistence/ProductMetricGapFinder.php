<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Persistence;

use PDOStatement;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\StatementInterface;
use Propel\Runtime\Propel;
use RuntimeException;

/**
 * Raw SQL, deliberately — see this package's README ("Product Value Gaps") for the full rationale. Short
 * version: this needs either a UNION of one query per active metric, or a genuine CROSS JOIN between
 * `spy_search_ranking_metric` and `spy_product_abstract` (two tables with no direct relation between
 * them — every real relation here runs through `spy_search_ranking_product_metric`'s own two foreign
 * keys). Propel 2.0's ActiveQuery has no UNION support at all, and a CROSS JOIN has no declared relation
 * for its generated `joinX()` methods to hang off. Every value that varies per call is bound as a
 * parameter; `$sortColumn`/`$sortDirection` are resolved through a fixed whitelist, never interpolated
 * from caller input directly.
 */
class ProductMetricGapFinder implements ProductMetricGapFinderInterface
{
    /**
     * @var string
     */
    protected const CONNECTION_NAME = 'zed';

    /**
     * @var array<string, string>
     */
    protected const SORT_COLUMN_MAP = [
        'sku' => 'product_abstract.sku',
        'missing_metric_name' => 'metric.name',
    ];

    /**
     * @var string
     */
    protected const DEFAULT_SORT_COLUMN = 'product_abstract.sku';

    /**
     * @var string
     */
    protected const BASE_SQL = <<<SQL
        FROM spy_product_abstract product_abstract
        CROSS JOIN spy_search_ranking_metric metric
        LEFT JOIN spy_search_ranking_product_metric product_metric
            ON product_metric.fk_product_abstract = product_abstract.id_product_abstract
            AND product_metric.fk_search_ranking_metric = metric.id_search_ranking_metric
        WHERE metric.is_active = 1
            AND product_metric.id_search_ranking_product_metric IS NULL
        SQL;

    /**
     * {@inheritDoc}
     *
     * @param int|null $idSearchRankingMetric
     * @param string $searchTerm
     * @param string $sortColumn
     * @param string $sortDirection
     * @param int $limit
     * @param int $offset
     *
     * @return array<int, array{id_product_abstract: int, sku: string, missing_metric_name: string}>
     */
    public function findGaps(
        ?int $idSearchRankingMetric,
        string $searchTerm,
        string $sortColumn,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        [$whereSql, $params] = $this->buildWhereClause($idSearchRankingMetric, $searchTerm);

        // LIMIT/OFFSET deliberately interpolated, not bound as `?` params: PDOStatement::execute()'s array
        // form binds every value as PARAM_STR, and MariaDB rejects a quoted string literal in LIMIT/OFFSET
        // outright. Safe here specifically because $limit/$offset are already strictly typed `int` by this
        // method's own signature — not caller-controlled strings — so there is nothing to inject.
        $sql = sprintf(
            'SELECT product_abstract.id_product_abstract, product_abstract.sku, metric.name AS missing_metric_name %s %s ORDER BY %s %s LIMIT %d OFFSET %d',
            static::BASE_SQL,
            $whereSql,
            $this->resolveSortColumn($sortColumn),
            $this->resolveSortDirection($sortDirection),
            $limit,
            $offset,
        );

        $statement = $this->prepareStatement($sql);
        $statement->execute($params);

        /** @var array<int, array{id_product_abstract: int, sku: string, missing_metric_name: string}> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * {@inheritDoc}
     *
     * @param int|null $idSearchRankingMetric
     *
     * @return int
     */
    public function countGaps(?int $idSearchRankingMetric): int
    {
        return $this->count($idSearchRankingMetric, '');
    }

    /**
     * {@inheritDoc}
     *
     * @param int|null $idSearchRankingMetric
     * @param string $searchTerm
     *
     * @return int
     */
    public function countFilteredGaps(?int $idSearchRankingMetric, string $searchTerm): int
    {
        return $this->count($idSearchRankingMetric, $searchTerm);
    }

    /**
     * @param int|null $idSearchRankingMetric
     * @param string $searchTerm
     *
     * @return int
     */
    protected function count(?int $idSearchRankingMetric, string $searchTerm): int
    {
        [$whereSql, $params] = $this->buildWhereClause($idSearchRankingMetric, $searchTerm);

        $sql = sprintf('SELECT COUNT(*) %s %s', static::BASE_SQL, $whereSql);

        $statement = $this->prepareStatement($sql);
        $statement->execute($params);

        return (int)$statement->fetchColumn();
    }

    /**
     * @param int|null $idSearchRankingMetric
     * @param string $searchTerm
     *
     * @return array{0: string, 1: array<int, int|string>}
     */
    protected function buildWhereClause(?int $idSearchRankingMetric, string $searchTerm): array
    {
        $whereSql = '';
        $params = [];

        if ($idSearchRankingMetric !== null) {
            $whereSql .= ' AND metric.id_search_ranking_metric = ?';
            $params[] = $idSearchRankingMetric;
        }

        if ($searchTerm !== '') {
            $whereSql .= ' AND (product_abstract.sku LIKE ? OR metric.name LIKE ?)';
            $likeTerm = '%' . $searchTerm . '%';
            $params[] = $likeTerm;
            $params[] = $likeTerm;
        }

        return [$whereSql, $params];
    }

    /**
     * @param string $sortColumn
     *
     * @return string
     */
    protected function resolveSortColumn(string $sortColumn): string
    {
        return static::SORT_COLUMN_MAP[$sortColumn] ?? static::DEFAULT_SORT_COLUMN;
    }

    /**
     * @param string $sortDirection
     *
     * @return string
     */
    protected function resolveSortDirection(string $sortDirection): string
    {
        return mb_strtolower($sortDirection) === 'desc' ? 'DESC' : 'ASC';
    }

    /**
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    protected function getConnection(): ConnectionInterface
    {
        return Propel::getServiceContainer()->getReadConnection(static::CONNECTION_NAME);
    }

    /**
     * `ConnectionInterface::prepare()` is declared to allow returning `false` on a driver-level prepare
     * failure — never expected in practice here (the SQL is this class's own fixed, package-authored
     * text, not caller-influenced), but a clear failure beats a confusing "call to a member function on
     * bool" fatal if it ever did happen.
     *
     * @param string $sql
     *
     * @throws \RuntimeException
     *
     * @return \Propel\Runtime\Connection\StatementInterface|\PDOStatement
     */
    protected function prepareStatement(string $sql): StatementInterface|PDOStatement
    {
        $statement = $this->getConnection()->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException(sprintf('Failed to prepare statement: %s', $sql));
        }

        return $statement;
    }
}
