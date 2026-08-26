<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Intent;

use Elastica\Client;
use Elastica\Exception\ExceptionInterface;
use Elastica\Request;

/**
 * The sole real-storage {@see EntityLookupInterface} implementation: backed by a slim, dedicated
 * OpenSearch index (`Schema/entity-lookup.json`) using the `completion` suggester field type — deliberately
 * NOT the heavy `page`/catalog content index (images, prices, full descriptions — the wrong tool for a
 * microsecond existence/prefix check). One physical index holds all three entity types
 * (`sku`/`brand`/`category`), distinguished by the `type` keyword field; one instance of this class is
 * scoped to exactly one `$entityType`. Always active for every type — see this package's README/install
 * checker for the one prerequisite (a live completion-suggester capability + a rebuilt index).
 *
 * `term` is mapped BOTH as a `completion` field (for {@see suggest()}, which needs real prefix/fuzzy
 * completion) and — via the separate `termNormalized` keyword field, populated with the SAME
 * {@see EntityTermNormalizer::normalize()} normalization used on both sides — for {@see exists()}, which
 * needs precise exact-match, not prefix completion (a completion suggester is optimized for prefix
 * matching, not exact-match lookups).
 *
 * Never throws: any Elastica/HTTP failure degrades to "no match" (`exists()` → `false`, `suggest()` → `[]`)
 * — the same graceful-degradation discipline {@see EntityLookupInterface} documents. This is the ONLY
 * safety net now (no separate tier-resolution/capability-probe layer sits in front of it any more): a real
 * query-time failure here degrades cleanly rather than 500ing a live catalog search, but a cluster that
 * genuinely cannot do completion suggesters at all will simply never detect an identifier/brand/category —
 * see `search-ranking:check-installation`.
 */
class SuggestIndexEntityLookup implements BatchableEntityLookupInterface
{
    /**
     * @var string
     */
    protected const FIELD_TERM = 'term';

    /**
     * @var string
     */
    protected const FIELD_TERM_NORMALIZED = 'termNormalized';

    /**
     * @var string
     */
    protected const FIELD_TYPE = 'type';

    /**
     * Real completion suggesters routinely return duplicate/near-duplicate entries (case variants, the same
     * term stored more than once) and are filtered post-hoc by `$entityType` here (a completion suggester
     * has no native way to filter its OWN candidate set by another field) — over-fetching by this bounded
     * multiplier keeps `suggest()` accurate up to `$limit` without an unbounded query.
     *
     * @var int
     */
    protected const SUGGEST_OVERFETCH_MULTIPLIER = 5;

    /**
     * @param \Elastica\Client $elasticaClient
     * @param string $indexName
     * @param string $entityType
     */
    public function __construct(
        protected Client $elasticaClient,
        protected string $indexName,
        protected string $entityType,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $term
     */
    public function exists(string $term): bool
    {
        $normalizedTerm = EntityTermNormalizer::normalize($term);

        if ($normalizedTerm === '') {
            return false;
        }

        $responseData = $this->requestData(Request::POST, [
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => [static::FIELD_TERM_NORMALIZED => $normalizedTerm]],
                        ['term' => [static::FIELD_TYPE => $this->entityType]],
                    ],
                ],
            ],
        ], '_count');

        if ($responseData === null) {
            return false;
        }

        return (int)($responseData['count'] ?? 0) > 0;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $prefix
     * @param int $limit
     *
     * @return array<int, string>
     */
    public function suggest(string $prefix, int $limit): array
    {
        if (trim($prefix) === '' || $limit <= 0) {
            return [];
        }

        $responseData = $this->requestData(Request::POST, [
            '_source' => [static::FIELD_TERM, static::FIELD_TYPE],
            'suggest' => [
                'entityLookupSuggest' => [
                    'prefix' => $prefix,
                    'completion' => [
                        'field' => static::FIELD_TERM,
                        'size' => $limit * static::SUGGEST_OVERFETCH_MULTIPLIER,
                        'fuzzy' => ['fuzziness' => 'AUTO'],
                    ],
                ],
            ],
        ], '_search');

        if ($responseData === null) {
            return [];
        }

        $options = $responseData['suggest']['entityLookupSuggest'][0]['options'] ?? [];

        if (!is_array($options)) {
            return [];
        }

        $matches = [];

        foreach ($options as $option) {
            $source = is_array($option['_source'] ?? null) ? $option['_source'] : [];

            if (($source[static::FIELD_TYPE] ?? null) !== $this->entityType) {
                continue;
            }

            $term = $source[static::FIELD_TERM] ?? null;

            if (!is_string($term) || $term === '' || in_array($term, $matches, true)) {
                continue;
            }

            $matches[] = $term;

            if (count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getIndexName(): string
    {
        return $this->indexName;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $term
     *
     * @return array<string, mixed>
     */
    public function buildBatchExistsProbeRequest(string $term): array
    {
        $normalizedTerm = EntityTermNormalizer::normalize($term);

        return [
            'size' => 0,
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => [static::FIELD_TERM_NORMALIZED => $normalizedTerm]],
                        ['term' => [static::FIELD_TYPE => $this->entityType]],
                    ],
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<string, mixed>|null $responseData
     */
    public function parseBatchExistsProbeResponse(?array $responseData): bool
    {
        if ($responseData === null) {
            return false;
        }

        $hits = is_array($responseData['hits'] ?? null) ? $responseData['hits'] : [];
        $total = is_array($hits['total'] ?? null) ? $hits['total'] : [];

        return (int)($total['value'] ?? 0) > 0;
    }

    /**
     * @param string $method
     * @param array<string, mixed> $data
     * @param string $path
     *
     * @return array<string, mixed>|null Null on any request failure — callers degrade to "no match".
     */
    protected function requestData(string $method, array $data, string $path): ?array
    {
        try {
            $responseData = $this->elasticaClient->request(
                $this->indexName . '/' . $path,
                $method,
                $data,
            )->getData();
        } catch (ExceptionInterface) {
            return null;
        }

        return $responseData;
    }
}
