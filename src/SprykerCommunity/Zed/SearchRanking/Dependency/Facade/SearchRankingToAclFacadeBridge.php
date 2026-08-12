<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Dependency\Facade;

use Generated\Shared\Transfer\GroupsTransfer;
use Generated\Shared\Transfer\RolesTransfer;
use Generated\Shared\Transfer\RulesTransfer;

class SearchRankingToAclFacadeBridge implements SearchRankingToAclFacadeInterface
{
    /**
     * @var \Spryker\Zed\Acl\Business\AclFacadeInterface
     */
    protected $aclFacade;

    /**
     * @param \Spryker\Zed\Acl\Business\AclFacadeInterface $aclFacade
     */
    public function __construct($aclFacade)
    {
        $this->aclFacade = $aclFacade;
    }

    public function getAllGroups(): GroupsTransfer
    {
        return $this->aclFacade->getAllGroups();
    }

    /**
     * @param int $idGroup
     */
    public function getGroupRoles(int $idGroup): RolesTransfer
    {
        return $this->aclFacade->getGroupRoles($idGroup);
    }

    /**
     * @param int $idRole
     */
    public function getRoleRules(int $idRole): RulesTransfer
    {
        return $this->aclFacade->getRoleRules($idRole);
    }
}
