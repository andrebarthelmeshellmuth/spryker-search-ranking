<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchRanking\Semantic;

use RuntimeException;

/**
 * Thrown by any {@see \SprykerCommunity\Client\SearchRanking\Semantic\EmbeddingClientInterface}
 * implementation when a text could not be embedded — connection failure, timeout, non-success HTTP
 * response, or a malformed response body. Never thrown FOR an empty input string; callers decide whether
 * that's even worth embedding.
 */
class EmbeddingUnavailableException extends RuntimeException
{
}
