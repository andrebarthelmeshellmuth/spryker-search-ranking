<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Intent;

use Elastica\Bulk;
use Elastica\Client;
use Elastica\Document;
use Elastica\Request;
use RuntimeException;
use SprykerCommunity\Client\SearchRanking\Intent\EntityTermNormalizer;

/**
 * Administrative (console-driven) index lifecycle for {@see \SprykerCommunity\Client\SearchRanking\Intent\SuggestIndexEntityLookup}'s
 * OpenSearch index — see {@see SuggestIndexEntityLookupIndexerInterface::ensureIndexExists()}'s own
 * docblock for why this is a simple create-if-missing lifecycle rather than a
 * `spryker-community/search-index-alias`-managed blue-green one. Runs from a Zed console
 * (`search-ranking:entity-lookup:suggest-index:rebuild`), never on a live search request path — failures
 * here are meant to be visible to the operator running the console, unlike every query-time collaborator in
 * this package, which must degrade silently.
 */
class SuggestIndexEntityLookupIndexer implements SuggestIndexEntityLookupIndexerInterface
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
     * @param \Elastica\Client $elasticaClient
     */
    public function __construct(protected Client $elasticaClient)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $indexName
     *
     * @throws \RuntimeException
     */
    public function ensureIndexExists(string $indexName): void
    {
        $existsResponse = $this->elasticaClient->request($indexName, Request::HEAD);

        if ($existsResponse->getStatus() === 200) {
            return;
        }

        $schema = $this->readSchema();

        $createResponse = $this->elasticaClient->request($indexName, Request::PUT, $schema);

        if (!$createResponse->isOk()) {
            throw new RuntimeException(sprintf(
                'Failed to create entity-lookup index "%s": %s',
                $indexName,
                (string)json_encode($createResponse->getData()),
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param string $indexName
     * @param string $type
     * @param array<int, string> $terms
     */
    public function replaceTerms(string $indexName, string $type, array $terms): int
    {
        $this->elasticaClient->request($indexName . '/_delete_by_query', Request::POST, [
            'query' => ['term' => [static::FIELD_TYPE => $type]],
        ]);

        $writtenCount = 0;

        if ($terms !== []) {
            $bulk = new Bulk($this->elasticaClient);
            $bulk->setIndex($indexName);

            foreach ($terms as $term) {
                $bulk->addDocument(new Document(null, [
                    static::FIELD_TERM => $term,
                    static::FIELD_TERM_NORMALIZED => EntityTermNormalizer::normalize($term),
                    static::FIELD_TYPE => $type,
                ]));
            }

            $bulk->send();
            $writtenCount = count($terms);
        }

        // Real completion-suggester/count queries fired right after this call (this package's own
        // live-verification, and CategoryAnalyzer/BrandAnalyzer's own tests against a real cluster) must
        // see the new documents immediately — OpenSearch's default ~1s refresh interval would otherwise
        // make them intermittently invisible.
        $this->refresh($indexName);

        return $writtenCount;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $indexName
     * @param string $type
     * @param array<int, string> $terms
     */
    public function upsertTerms(string $indexName, string $type, array $terms): int
    {
        if ($terms === []) {
            return 0;
        }

        $bulk = new Bulk($this->elasticaClient);
        $bulk->setIndex($indexName);

        foreach ($terms as $term) {
            $termNormalized = EntityTermNormalizer::normalize($term);

            $bulk->addDocument(new Document($this->buildDeterministicDocumentId($type, $termNormalized), [
                static::FIELD_TERM => $term,
                static::FIELD_TERM_NORMALIZED => $termNormalized,
                static::FIELD_TYPE => $type,
            ]));
        }

        $bulk->send();
        $this->refresh($indexName);

        return count($terms);
    }

    /**
     * {@inheritDoc}
     *
     * @param string $indexName
     * @param string $type
     * @param array<int, string> $terms
     */
    public function removeTerms(string $indexName, string $type, array $terms): int
    {
        if ($terms === []) {
            return 0;
        }

        $termsNormalized = array_map(EntityTermNormalizer::normalize(...), $terms);

        $response = $this->elasticaClient->request($indexName . '/_delete_by_query', Request::POST, [
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => [static::FIELD_TYPE => $type]],
                        ['terms' => [static::FIELD_TERM_NORMALIZED => $termsNormalized]],
                    ],
                ],
            ],
        ]);

        $this->refresh($indexName);

        $responseData = $response->getData();

        return isset($responseData['deleted']) ? (int)$responseData['deleted'] : 0;
    }

    /**
     * A deterministic id (rather than a random/auto one) is what makes {@see upsertTerms()} idempotent —
     * calling it twice for the same `$type`/normalized term overwrites the same document instead of
     * creating a duplicate. Not used by {@see replaceTerms()} (its documents keep random auto-generated
     * ids, unchanged) — both id schemes coexist safely because {@see removeTerms()} and the `_delete_by_query`
     * inside {@see replaceTerms()} both match on the `type`/`termNormalized` FIELDS, never on the id.
     *
     * @param string $type
     * @param string $termNormalized
     */
    protected function buildDeterministicDocumentId(string $type, string $termNormalized): string
    {
        return md5($type . ':' . $termNormalized);
    }

    /**
     * @param string $indexName
     */
    protected function refresh(string $indexName): void
    {
        $this->elasticaClient->request($indexName . '/_refresh', Request::POST, []);
    }

    /**
     * `entity-lookup.json` is deliberately a TYPELESS mapping (`{"mappings": {"properties": {...}}}`), NOT
     * the legacy `{"mappings": {"<type>": {"properties": {...}}}}` shape `page.json` uses — that wrapped
     * shape only works because core's own `search:setup`/`IndexDefinitionBuilder` pipeline strips the type
     * key before sending it to the engine; this class PUTs the file's contents to the engine directly
     * (see {@see ensureIndexExists()}'s own docblock for why this index is not managed through that
     * pipeline), so it must already be in the shape OpenSearch 1.3.4 actually accepts for a raw `PUT
     * <index>` — verified live: the wrapped shape was rejected with "Root mapping definition has
     * unsupported parameters".
     *
     * @throws \RuntimeException
     *
     * @return array<string, mixed>
     */
    protected function readSchema(): array
    {
        $schemaPath = dirname(__DIR__, 4) . '/Shared/SearchRanking/Schema/entity-lookup.json';
        $schemaJson = file_get_contents($schemaPath);

        if ($schemaJson === false) {
            throw new RuntimeException(sprintf('Could not read entity-lookup schema at "%s".', $schemaPath));
        }

        $schema = json_decode($schemaJson, true);

        if (!is_array($schema)) {
            throw new RuntimeException(sprintf('Entity-lookup schema at "%s" is not valid JSON.', $schemaPath));
        }

        return $schema;
    }
}
