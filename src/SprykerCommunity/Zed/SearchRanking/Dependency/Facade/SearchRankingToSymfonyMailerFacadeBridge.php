<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Dependency\Facade;

use Generated\Shared\Transfer\MailTransfer;

class SearchRankingToSymfonyMailerFacadeBridge implements SearchRankingToSymfonyMailerFacadeInterface
{
    /**
     * @var \Spryker\Zed\SymfonyMailer\Business\SymfonyMailerFacadeInterface
     */
    protected $symfonyMailerFacade;

    /**
     * @param \Spryker\Zed\SymfonyMailer\Business\SymfonyMailerFacadeInterface $symfonyMailerFacade
     */
    public function __construct($symfonyMailerFacade)
    {
        $this->symfonyMailerFacade = $symfonyMailerFacade;
    }

    /**
     * @param \Generated\Shared\Transfer\MailTransfer $mailTransfer
     *
     * @return void
     */
    public function send(MailTransfer $mailTransfer): void
    {
        $this->symfonyMailerFacade->send($mailTransfer);
    }
}
