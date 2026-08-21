<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use SprykerCommunity\Zed\SearchRankingGui\Communication\Form\ScopeCopyRunActionForm;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * One-off "Copy now" — copies the source scope's weight/setting AND formula/isActive/shape configuration
 * onto the target scope without creating a lock. See {@see ScopeCopyLockController} for the persistent,
 * daily-resynced version (weight/setting only after the initial bootstrap — see that controller's own
 * docblock).
 *
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class ScopeCopyRunController extends AbstractScopeCopyController
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function indexAction(Request $request): RedirectResponse
    {
        [$sourceStoreName, $sourceLocaleName, $targetStoreName, $targetLocaleName] = $this->resolveScopeCopyParamsFromQuery($request);

        $redirectUrl = $this->buildScopeCopyRedirectUrl($sourceStoreName, $sourceLocaleName, $targetStoreName, $targetLocaleName);

        $actionForm = $this->getFactory()->createScopeCopyRunActionForm()->handleRequest($request);

        if (!$actionForm->isSubmitted() || !$actionForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse($redirectUrl);
        }

        $confirmOverwrite = (bool)$actionForm->get(ScopeCopyRunActionForm::FIELD_CONFIRM_OVERWRITE)->getData();
        $mode = (string)$actionForm->get(ScopeCopyRunActionForm::FIELD_MODE)->getData();

        $resultTransfer = $this->getFactory()->getSearchRankingFacade()->copyFullScopeConfiguration(
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $mode,
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

        $skippedCount = $resultTransfer->getSkippedCount() + $resultTransfer->getStoreConfigSkippedCount();

        $this->addSuccessMessage(sprintf(
            'Copied %d metric weight(s), %d setting(s) and %d store-config metric(s) from %s/%s to %s/%s.%s',
            $resultTransfer->getMetricWeightCopiedCount(),
            $resultTransfer->getSettingCopiedCount(),
            $resultTransfer->getStoreConfigCopiedCount(),
            $sourceStoreName,
            $sourceLocaleName,
            $targetStoreName,
            $targetLocaleName,
            $skippedCount ? sprintf(' Skipped %d item(s) the target has not adopted yet.', $skippedCount) : '',
        ));

        return $this->redirectResponse($redirectUrl);
    }
}
