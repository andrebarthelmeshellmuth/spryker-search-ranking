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
 * One-off "Sync now" for the store-config (formula/isActive/shape) copy action — store-only, unlike
 * {@see ScopeCopyRunController}'s (store,locale)-scoped weight/setting copy. No lockable/cron-synced
 * variant exists for this action (a deliberate Phase 7 decision, see project memory: formula/k tuning
 * changes far less often than weight).
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
        $sourceStoreName = (string)$request->query->get(ScopeCopyController::PARAM_SOURCE_STORE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME;
        $sourceLocaleName = (string)$request->query->get(ScopeCopyController::PARAM_SOURCE_LOCALE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME;
        $targetStoreName = (string)$request->query->get(ScopeCopyController::PARAM_TARGET_STORE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME;
        $targetLocaleName = (string)$request->query->get(ScopeCopyController::PARAM_TARGET_LOCALE_NAME, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME;

        $redirectUrl = sprintf(
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

        $actionForm = $this->getFactory()->createStoreConfigSyncActionForm()->handleRequest($request);

        if (!$actionForm->isSubmitted() || !$actionForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse($redirectUrl);
        }

        $confirmOverwrite = (bool)$actionForm->get(StoreConfigSyncActionForm::FIELD_CONFIRM_OVERWRITE)->getData();
        $mode = (string)$actionForm->get(StoreConfigSyncActionForm::FIELD_MODE)->getData();

        $resultTransfer = $this->getFactory()->getSearchRankingFacade()->copyStoreConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $mode,
            $confirmOverwrite,
        );

        if ($resultTransfer->getIsBlockedByExistingData()) {
            $this->addErrorMessage(sprintf(
                '%s already has saved store configuration — check "Overwrite existing target store configuration" and sync again to proceed.',
                $targetStoreName,
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
            $sourceStoreName,
            $targetStoreName,
            $skippedCount ? sprintf(' Skipped %d metric(s) the target has not adopted yet.', $skippedCount) : '',
        ));

        return $this->redirectResponse($redirectUrl);
    }
}
