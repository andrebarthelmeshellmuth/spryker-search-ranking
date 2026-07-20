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
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class SettingsForm extends AbstractType
{
    /**
     * @var string
     */
    public const FIELD_RELEVANCE_WEIGHT = 'relevanceWeight';

    /**
     * @var string
     */
    public const FIELD_RELEVANCE_SATURATION_POINT = 'relevanceSaturationPoint';

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add(static::FIELD_RELEVANCE_WEIGHT, NumberType::class, [
            'label' => 'Relevance weight',
            'help' => 'Share of the final ranking score that comes from normalized text relevance, in the formula: '
                . 'relevanceWeight * (text relevance score / (text relevance score + relevance saturation point)) '
                . '+ (1 - relevanceWeight) * weighted business signals. '
                . 'Must be between 0 (ranking driven entirely by business signals) and 1 (ranking driven entirely by text relevance).',
            'constraints' => [
                new NotBlank(),
                new Range(['min' => 0, 'max' => 1]),
            ],
        ]);

        $builder->add(static::FIELD_RELEVANCE_SATURATION_POINT, NumberType::class, [
            'label' => 'Relevance saturation point',
            'help' => 'The raw Elasticsearch text relevance score at which normalized relevance reaches 0.5 — '
                . 'the point the blend formula above saturates around. Elasticsearch scores are unbounded and '
                . 'depend on the query itself, so this has no universal correct value: read typical scores off '
                . 'the search-debug overlay for this shop\'s own catalog and queries, and tune from there. Must '
                . 'be greater than 0.',
            'constraints' => [
                new NotBlank(),
                new GreaterThan(0),
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
