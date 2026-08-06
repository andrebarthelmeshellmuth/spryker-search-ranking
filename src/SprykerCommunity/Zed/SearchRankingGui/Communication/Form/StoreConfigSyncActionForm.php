<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form;

use Spryker\Zed\Kernel\Communication\Form\AbstractType;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Backs the "Sync now" button on the Scope Copy page's store-config section — same shape as
 * {@see ScopeCopyActionForm}, plus the one extra field this action needs: which of
 * {@see StoreConfigCopierInterface}::MODE_* to run. The 2 store names ride in the form's `action` URL,
 * same as every other GET-selected-scope page in this module, not as form fields.
 *
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class StoreConfigSyncActionForm extends AbstractType
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
                'Mirror (create missing metrics too)' => StoreConfigCopierInterface::MODE_MIRROR,
                'Copy only metrics the target already has' => StoreConfigCopierInterface::MODE_COPY_ONLY_OVERLAP,
            ],
            'data' => StoreConfigCopierInterface::MODE_MIRROR,
            'expanded' => true,
            'multiple' => false,
        ]);

        $builder->add(static::FIELD_CONFIRM_OVERWRITE, CheckboxType::class, [
            'label' => 'Overwrite existing target store configuration',
            'required' => false,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'search_ranking_store_config_sync_action';
    }
}
