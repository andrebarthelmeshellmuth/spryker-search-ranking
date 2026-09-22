<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Search;

use Elastica\Client;
use Elastica\JSON;
use Elastica\Request;
use Throwable;

/**
 * {@inheritDoc}
 *
 * Fires a raw `_msearch` request via `Elastica\Client::request()` — the same "raw Elastica client request
 * against a resolved index name" pattern this package's own {@see QueryTermFrequencyFetcher} and
 * `Search/EngineCompatibilityChecker` already use, deliberately NOT `Elastica\Client::request()`'s own
 * higher-level multi-search helpers (none of which exist for the raw NDJSON shape this cluster-wide,
 * cross-index batch needs). `_msearch` is cluster-wide (no index in the request PATH) — each probe's own
 * header line carries its own `index` field instead, which is what lets probes against different indices
 * (the `page` index for specificity, a separate suggest index for entity lookups) share one request.
 *
 * The request body is newline-delimited JSON (`Elastica\Request::NDJSON_CONTENT_TYPE`), built as a plain
 * PHP string and passed through `Elastica\Client::request()`'s `$data` parameter as a STRING, not an array
 * — Elastica's own HTTP transport only JSON-encodes an array `$data`; a string is sent verbatim, which is
 * exactly what NDJSON's "one line per header, one line per body" shape requires (a single `json_encode()`
 * over the whole batch would produce one JSON document, not NDJSON).
 */
class MsearchProbeBatcher implements MsearchProbeBatcherInterface
{
    /**
     * @var string
     */
    protected const ENDPOINT_MSEARCH = '_msearch';

    /**
     * @var array<string, array{key: string, indexName: string, queryBody: array<string, mixed>}>
     */
    protected array $pendingRegistrations = [];

    /**
     * @var array<string, array<string, mixed>|null>
     */
    protected array $responsesByKey = [];

    /**
     * @param \Elastica\Client $elasticaClient
     */
    public function __construct(protected Client $elasticaClient)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $key
     * @param string $indexName
     * @param array<string, mixed> $queryBody
     */
    public function registerProbe(string $key, string $indexName, array $queryBody): void
    {
        $this->pendingRegistrations[$key] = ['key' => $key, 'indexName' => $indexName, 'queryBody' => $queryBody];
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function execute(): void
    {
        if ($this->pendingRegistrations === []) {
            // Load-bearing no-op: never fire an empty `_msearch`. Whatever an earlier batch's responses
            // were stay retrievable — nothing to overwrite when there is nothing new to run.
            return;
        }

        $registrations = array_values($this->pendingRegistrations);
        $this->pendingRegistrations = [];

        try {
            $responseData = $this->elasticaClient->request(
                static::ENDPOINT_MSEARCH,
                Request::POST,
                $this->buildNdjsonBody($registrations),
                [],
                Request::NDJSON_CONTENT_TYPE,
            )->getData();
        } catch (Throwable) {
            // Transport-level failure (connection error, cluster unreachable): every probe in THIS batch
            // degrades to "no response" — see MsearchProbeBatcherInterface::execute()'s own contract.
            foreach ($registrations as $registration) {
                $this->responsesByKey[$registration['key']] = null;
            }

            return;
        }

        $responses = is_array($responseData['responses'] ?? null) ? $responseData['responses'] : [];

        foreach ($registrations as $index => $registration) {
            $this->responsesByKey[$registration['key']] = is_array($responses[$index] ?? null) ? $responses[$index] : null;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $key
     *
     * @return array<string, mixed>|null
     */
    public function getResponseFor(string $key): ?array
    {
        return $this->responsesByKey[$key] ?? null;
    }

    /**
     * @param array<int, array{key: string, indexName: string, queryBody: array<string, mixed>}> $registrations
     */
    protected function buildNdjsonBody(array $registrations): string
    {
        $lines = [];

        foreach ($registrations as $registration) {
            $lines[] = JSON::stringify(['index' => $registration['indexName']]);
            $lines[] = JSON::stringify($registration['queryBody']);
        }

        return implode("\n", $lines) . "\n";
    }
}
