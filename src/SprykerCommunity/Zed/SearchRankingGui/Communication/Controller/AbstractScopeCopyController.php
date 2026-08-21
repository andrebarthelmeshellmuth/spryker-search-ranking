<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
abstract class AbstractScopeCopyController extends AbstractController
{
    /**
     * @var string
     */
    protected const URL_SCOPE_COPY = '/search-ranking-gui/scope-copy';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $paramName
     * @param string $defaultValue
     */
    protected function resolveQueryParam(Request $request, string $paramName, string $defaultValue): string
    {
        return (string)$request->query->get($paramName, '') ?: $defaultValue;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function resolveScopeCopyParamsFromQuery(Request $request): array
    {
        return [
            $this->resolveQueryParam($request, ScopeCopyController::PARAM_SOURCE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME),
            $this->resolveQueryParam($request, ScopeCopyController::PARAM_SOURCE_LOCALE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME),
            $this->resolveQueryParam($request, ScopeCopyController::PARAM_TARGET_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME),
            $this->resolveQueryParam($request, ScopeCopyController::PARAM_TARGET_LOCALE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME),
        ];
    }

    /**
     * @param string $sourceStoreName
     * @param string $sourceLocaleName
     * @param string $targetStoreName
     * @param string $targetLocaleName
     */
    protected function buildScopeCopyRedirectUrl(
        string $sourceStoreName,
        string $sourceLocaleName,
        string $targetStoreName,
        string $targetLocaleName,
    ): string {
        return sprintf(
            '%s?%s=%s&%s=%s&%s=%s&%s=%s',
            static::URL_SCOPE_COPY,
            ScopeCopyController::PARAM_SOURCE_STORE_NAME,
            $sourceStoreName,
            ScopeCopyController::PARAM_SOURCE_LOCALE_NAME,
            $sourceLocaleName,
            ScopeCopyController::PARAM_TARGET_STORE_NAME,
            $targetStoreName,
            ScopeCopyController::PARAM_TARGET_LOCALE_NAME,
            $targetLocaleName,
        );
    }
}
