<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class ScopeCopyUnlockController extends AbstractController
{
    /**
     * @var string
     */
    protected const URL_SCOPE_COPY = '/search-ranking-gui/scope-copy';

    /**
     * @var string
     */
    protected const PARAM_ID_SEARCH_RANKING_SCOPE_COPY_LOCK = 'idSearchRankingScopeCopyLock';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function indexAction(Request $request): RedirectResponse
    {
        $unlockForm = $this->getFactory()->createScopeCopyUnlockForm()->handleRequest($request);

        if (!$unlockForm->isSubmitted() || !$unlockForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse(static::URL_SCOPE_COPY);
        }

        $idSearchRankingScopeCopyLock = $this->castId(
            $request->query->get(static::PARAM_ID_SEARCH_RANKING_SCOPE_COPY_LOCK),
        );

        $this->getFactory()->getSearchRankingFacade()->deactivateScopeCopyLock($idSearchRankingScopeCopyLock);
        $this->addSuccessMessage('Lock removed.');

        return $this->redirectResponse(static::URL_SCOPE_COPY);
    }
}
