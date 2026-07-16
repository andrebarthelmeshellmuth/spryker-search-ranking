<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form;

use Spryker\Zed\Kernel\Communication\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class SettingsForm extends AbstractType
{
    /**
     * @var string
     */
    public const FIELD_SCORE_FLOOR = 'scoreFloor';

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add(static::FIELD_SCORE_FLOOR, NumberType::class, [
            'label' => 'Score floor',
            'help' => 'Additive floor of the ranking formula: (1 + sqrt(text relevance)) * (weighted signals + floor). '
                . 'Keeps products without business signals from being ranked towards zero. 0 means signals fully gate the score.',
            'constraints' => [
                new NotBlank(),
                new GreaterThanOrEqual(0),
            ],
        ]);
    }

    /**
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'search_ranking_settings';
    }
}
