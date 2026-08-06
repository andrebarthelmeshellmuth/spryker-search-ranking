<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form;

use Spryker\Zed\Kernel\Communication\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Backs both the "Copy now" and "Lock" buttons on the Scope Copy page — same CSRF protection every Zed
 * form gets automatically (see {@see \SprykerCommunity\Zed\SearchRankingGui\Communication\Form\NormalizeWeightsForm}),
 * plus the one real field this action needs: the "yes, overwrite" confirmation the target-already-has-data
 * guard requires. The 4 scope names themselves ride in the form's `action` URL (set at render time,
 * same as every other GET-selected-scope page in this module), not as form fields.
 *
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class ScopeCopyActionForm extends AbstractType
{
    /**
     * @var string
     */
    public const FIELD_CONFIRM_OVERWRITE = 'confirmOverwrite';

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add(static::FIELD_CONFIRM_OVERWRITE, CheckboxType::class, [
            'label' => 'Overwrite existing target configuration',
            'required' => false,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'search_ranking_scope_copy_action';
    }
}
