<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form;

use Spryker\Zed\Kernel\Communication\Form\AbstractType;

/**
 * No fields of its own — this exists purely for the CSRF protection every Zed form gets automatically
 * (same mechanism {@see SettingsForm} already relies on), for the "Normalize active weights" button on
 * the metric list page. A plain link (like "Create Metric"/"View Product Values" on that same page)
 * would work for navigation, but this action WRITES (persists normalized weights), so it needs the same
 * CSRF-protected POST treatment every other mutating action in this module gets.
 *
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class NormalizeWeightsForm extends AbstractType
{
    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'search_ranking_normalize_weights';
    }
}
