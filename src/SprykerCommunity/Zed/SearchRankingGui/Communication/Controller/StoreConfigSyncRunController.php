<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\StoreConfigSyncActionForm;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * One-off "Sync now" for the store-config (formula/isActive/shape) copy action, with its own independent
 * source/target store+locale pickers (deliberately separate from {@see ScopeCopyRunController}'s ones —
 * see {@see ScopeCopyController::PARAM_SYNC_SOURCE_STORE_NAME}). No lockable/cron-synced variant exists
 * for this action — a deliberate choice: formula/k tuning changes far less often than weight, so a
 * recurring sync would mostly re-copy an unchanged value.
 *
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class StoreConfigSyncRunController extends AbstractController
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
        $syncSourceStoreName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SYNC_SOURCE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME);
        $syncTargetStoreName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SYNC_TARGET_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME);
        $syncSourceLocaleName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SYNC_SOURCE_LOCALE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME);
        $syncTargetLocaleName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SYNC_TARGET_LOCALE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME);
        // Carried through as hidden fields on the same form purely so the "Copy configuration between
        // store/locale scopes" widget's own picker doesn't reset back to its defaults on this redirect.
        $sourceStoreName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SOURCE_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME);
        $sourceLocaleName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_SOURCE_LOCALE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME);
        $targetStoreName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_TARGET_STORE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME);
        $targetLocaleName = $this->resolveQueryParam($request, ScopeCopyController::PARAM_TARGET_LOCALE_NAME, SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME);

        $redirectUrl = sprintf(
            '%s?%s=%s&%s=%s&%s=%s&%s=%s&%s=%s&%s=%s&%s=%s&%s=%s',
            static::URL_SCOPE_COPY,
            ScopeCopyController::PARAM_SYNC_SOURCE_STORE_NAME,
            $syncSourceStoreName,
            ScopeCopyController::PARAM_SYNC_TARGET_STORE_NAME,
            $syncTargetStoreName,
            ScopeCopyController::PARAM_SYNC_SOURCE_LOCALE_NAME,
            $syncSourceLocaleName,
            ScopeCopyController::PARAM_SYNC_TARGET_LOCALE_NAME,
            $syncTargetLocaleName,
            ScopeCopyController::PARAM_SOURCE_STORE_NAME,
            $sourceStoreName,
            ScopeCopyController::PARAM_SOURCE_LOCALE_NAME,
            $sourceLocaleName,
            ScopeCopyController::PARAM_TARGET_STORE_NAME,
            $targetStoreName,
            ScopeCopyController::PARAM_TARGET_LOCALE_NAME,
            $targetLocaleName,
        );

        $actionForm = $this->getFactory()->createStoreConfigSyncActionForm()->handleRequest($request);

        if (!$actionForm->isSubmitted() || !$actionForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse($redirectUrl);
        }

        $confirmOverwrite = (bool)$actionForm->get(StoreConfigSyncActionForm::FIELD_CONFIRM_OVERWRITE)->getData();
        $mode = (string)$actionForm->get(StoreConfigSyncActionForm::FIELD_MODE)->getData();

        $resultTransfer = $this->getFactory()->getSearchRankingFacade()->copyStoreConfiguration(
            $syncSourceStoreName,
            $syncSourceLocaleName,
            $syncTargetStoreName,
            $syncTargetLocaleName,
            $mode,
            $confirmOverwrite,
        );

        if ($resultTransfer->getIsBlockedByExistingData()) {
            $this->addErrorMessage(sprintf(
                '%s already has saved store configuration — check "Overwrite existing target store configuration" and sync again to proceed.',
                $syncTargetStoreName,
            ));

            return $this->redirectResponse($redirectUrl);
        }

        if (!$resultTransfer->getIsSuccess()) {
            $this->addErrorMessage((string)$resultTransfer->getErrorMessage());

            return $this->redirectResponse($redirectUrl);
        }

        $skippedCount = $resultTransfer->getSkippedCount();

        $this->addSuccessMessage(sprintf(
            'Synced %d metric(s) from %s to %s.%s',
            $resultTransfer->getCopiedCount(),
            $syncSourceStoreName,
            $syncTargetStoreName,
            $skippedCount ? sprintf(' Skipped %d metric(s) the target has not adopted yet.', $skippedCount) : '',
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
