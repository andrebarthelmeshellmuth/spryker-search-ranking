<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form;

use Spryker\Zed\Kernel\Communication\Form\AbstractType;

/**
 * No fields of its own — same CSRF-only shape as {@see NormalizeWeightsForm}, one per row of the
 * active-locks table on the Scope Copy page. The lock id rides in the form's `action` URL.
 *
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class ScopeCopyUnlockForm extends AbstractType
{
    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'search_ranking_scope_copy_unlock';
    }
}
