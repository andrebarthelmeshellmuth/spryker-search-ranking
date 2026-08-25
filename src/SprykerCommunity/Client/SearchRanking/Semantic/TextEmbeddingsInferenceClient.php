<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Semantic;

use JsonException;

/**
 * Talks to a Hugging Face Text Embeddings Inference (TEI) server's `/embed` HTTP endpoint via plain PHP
 * curl functions — no new composer dependency, matching this codebase's minimal-deps posture. Request body
 * is `{"inputs": "..."}`; response is a JSON array of arrays (even for a single input), e.g.
 * `[[0.123, -0.045, ...]]` — this client returns `$response[0]`.
 *
 * Deliberately holds no TEI-specific assumptions in {@see EmbeddingClientInterface} itself — this class is
 * one interchangeable implementation, kept swappable for a future external-embedding-API implementation.
 */
class TextEmbeddingsInferenceClient implements EmbeddingClientInterface
{
    /**
     * @var string
     */
    protected const EMBED_PATH = '/embed';

    /**
     * @param string $baseUrl Base URL of the TEI server, e.g. `http://embeddings:80` (no trailing slash).
     * @param int $connectTimeoutMilliseconds Connect-phase timeout — short, since this is a local-network call.
     * @param int $timeoutMilliseconds Total request timeout, including model inference time.
     */
    public function __construct(
        protected string $baseUrl,
        protected int $connectTimeoutMilliseconds = 500,
        protected int $timeoutMilliseconds = 2000,
    ) {
    }

    /**
     * @param string $text
     *
     * @throws \SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $url = rtrim($this->baseUrl, '/') . static::EMBED_PATH;

        if ($url === '') {
            throw new EmbeddingUnavailableException('Embedding service base URL is empty.');
        }

        $curlHandle = curl_init();

        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, (string)json_encode(['inputs' => $text], JSON_THROW_ON_ERROR));
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeoutMilliseconds);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT_MS, $this->timeoutMilliseconds);

        $responseBody = curl_exec($curlHandle);
        $curlErrorNumber = curl_errno($curlHandle);
        $curlError = curl_error($curlHandle);
        $statusCode = (int)curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        if ($curlErrorNumber !== 0) {
            throw new EmbeddingUnavailableException(sprintf(
                'Could not reach the embedding service at "%s": %s (curl errno %d).',
                $this->baseUrl,
                $curlError,
                $curlErrorNumber,
            ));
        }

        if ($statusCode !== 200 || !is_string($responseBody)) {
            throw new EmbeddingUnavailableException(sprintf(
                'Embedding service at "%s" returned HTTP %d.',
                $this->baseUrl,
                $statusCode,
            ));
        }

        return $this->parseResponseBody($responseBody);
    }

    /**
     * @param string $responseBody
     *
     * @throws \SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingUnavailableException
     *
     * @return array<int, float>
     */
    protected function parseResponseBody(string $responseBody): array
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new EmbeddingUnavailableException(sprintf(
                'Embedding service returned a response body that is not valid JSON: %s',
                $jsonException->getMessage(),
            ), 0, $jsonException);
        }

        if (!is_array($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            throw new EmbeddingUnavailableException(
                'Embedding service returned a response body that is not the expected array-of-vectors shape.',
            );
        }

        return array_map('floatval', $decoded[0]);
    }
}
