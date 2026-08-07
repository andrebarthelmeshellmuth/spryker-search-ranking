<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\ScopeCopyActionForm;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * One-off "Copy now" — copies the source scope's configuration onto the target scope without creating a
 * lock. See {@see ScopeCopyLockController} for the persistent, daily-resynced version.
 *
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class ScopeCopyRunController extends AbstractController
{
    /**
     * @var string
     */
    protected const URL_SCOPE_COPY = '/search-ranking-gui/scope-copy';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function indexAction(Request $request): RedirectResponse
    {
        $sourceStoreName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SOURCE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME);
        $sourceLocaleName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SOURCE_LOCALE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME);
        $targetStoreName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_TARGET_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME);
        $targetLocaleName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_TARGET_LOCALE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME);
        // Carried through as hidden fields on the same form purely so the independent "Sync store
        // configuration" widget's own picker doesn't reset back to its defaults on this redirect.
        $syncSourceStoreName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SYNC_SOURCE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME);
        $syncTargetStoreName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SYNC_TARGET_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME);

        $redirectUrl = sprintf(
            '%s?%s=%s&%s=%s&%s=%s&%s=%s&%s=%s&%s=%s',
            static::URL_SCOPE_COPY,
            ScopeCopyController::PARAM_SOURCE_STORE_NAME,
            $sourceStoreName,
            ScopeCopyController::PARAM_SOURCE_LOCALE_NAME,
            $sourceLocaleName,
            ScopeCopyController::PARAM_TARGET_STORE_NAME,
            $targetStoreName,
            ScopeCopyController::PARAM_TARGET_LOCALE_NAME,
            $targetLocaleName,
            ScopeCopyController::PARAM_SYNC_SOURCE_STORE_NAME,
            $syncSourceStoreName,
            ScopeCopyController::PARAM_SYNC_TARGET_STORE_NAME,
            $syncTargetStoreName,
        );

        $actionForm = $this->getFactory()->createScopeCopyActionForm()->handleRequest($request);

        if (!$actionForm->isSubmitted() || !$actionForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse($redirectUrl);
        }

        $confirmOverwrite = (bool)$actionForm->get(ScopeCopyActionForm::FIELD_CONFIRM_OVERWRITE)->getData();

        $resultTransfer = $this->getFactory()->getSearchRankingFacade()->copyScopeConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $confirmOverwrite,
        );

        if ($resultTransfer->getIsBlockedByExistingData()) {
            $this->addErrorMessage(sprintf(
                '%s/%s already has saved configuration — check "Overwrite existing target configuration" and copy again to proceed.',
                $targetStoreName,
                $targetLocaleName,
            ));

            return $this->redirectResponse($redirectUrl);
        }

        if (!$resultTransfer->getIsSuccess()) {
            $this->addErrorMessage((string)$resultTransfer->getErrorMessage());

            return $this->redirectResponse($redirectUrl);
        }

        $this->addSuccessMessage(sprintf(
            'Copied %d metric weight(s) and %d setting(s) from %s/%s to %s/%s.',
            $resultTransfer->getMetricWeightCopiedCount(),
            $resultTransfer->getSettingCopiedCount(),
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
        ));

        return $this->redirectResponse($redirectUrl);
    }

    /**
     * Extracted purely to keep indexAction()'s own size/complexity down — every one of this controller's
     * several query params follows the identical "explicit value or fall back to a default" resolution,
     * no behavioral change from inlining it.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $paramName
     * @param string $defaultValue
     */
    protected function resolveQueryParam(Request $request, string $paramName, string $defaultValue): string
    {
        return (string)$request->query->get($paramName, '') ?: $defaultValue;
    }
}
