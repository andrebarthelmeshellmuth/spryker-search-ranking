<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form;

use Spryker\Zed\Kernel\Communication\Form\AbstractType;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopierInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Backs the "Copy now" button on the Scope Copy page — same shape as {@see ScopeCopyActionForm} (which
 * still backs "Lock" on its own, always at `MODE_MIRROR` — see {@see \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockManagerInterface::createScopeCopyLock()}),
 * plus the one extra field a one-off copy offers a choice on: which of
 * {@see ScopeConfigCopierInterface}::MODE_* to run, applied identically to both halves of the combined
 * copy. The 4 scope names ride in the form's `action` URL, same as every other GET-selected-scope page in
 * this module, not as form fields.
 *
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class ScopeCopyRunActionForm extends AbstractType
{
    /**
     * @var string
     */
    public const FIELD_CONFIRM_OVERWRITE = 'confirmOverwrite';

    /**
     * @var string
     */
    public const FIELD_MODE = 'mode';

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add(static::FIELD_MODE, ChoiceType::class, [
            'label' => 'Mode',
            'choices' => [
                'Mirror (create missing metrics/settings too)' => ScopeConfigCopierInterface::MODE_MIRROR,
                'Copy only what the target already has' => ScopeConfigCopierInterface::MODE_COPY_ONLY_OVERLAP,
            ],
            'data' => ScopeConfigCopierInterface::MODE_MIRROR,
            'expanded' => true,
            'multiple' => false,
        ]);

        $builder->add(static::FIELD_CONFIRM_OVERWRITE, CheckboxType::class, [
            'label' => 'Overwrite existing target configuration',
            'required' => false,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'search_ranking_scope_copy_run_action';
    }
}
