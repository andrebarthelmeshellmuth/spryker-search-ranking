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
     * @var string
     */
    public const FIELD_SPECIFICITY_BLEND_WEIGHT = 'specificityBlendWeight';

    /**
     * @var string
     */
    public const FIELD_SPECIFICITY_SATURATION_POINT = 'specificitySaturationPoint';

    /**
     * @var string
     */
    public const FIELD_SPECIFICITY_WEIGHT_EXPONENT = 'specificityWeightExponent';

    /**
     * @var string
     */
    public const FIELD_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE = 'specificityWeightShiftMagnitude';

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
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

        $builder->add(static::FIELD_SPECIFICITY_BLEND_WEIGHT, NumberType::class, [
            'label' => 'Specificity blend weight',
            'help' => 'Only takes effect if specificity-aware relevance weighting is enabled in code '
                . '(SearchRankingConfig::isSpecificityWeightingEnabled(), off by default). Share of the '
                . 'raw specificity value coming from the query\'s single rarest term (max idf), vs. '
                . '(1 - this) coming from the harmonic mean of every term\'s idf. Higher favors a query '
                . 'with one very rare term (e.g. a SKU) even if its other words are common; lower '
                . 'penalizes a query as soon as any one term is common. Must be between 0 and 1. Also '
                . 'tunable via spryker-community/search-ranking-optimizer.',
            'constraints' => [
                new NotBlank(),
                new Range(['min' => 0, 'max' => 1]),
            ],
        ]);

        $builder->add(static::FIELD_SPECIFICITY_SATURATION_POINT, NumberType::class, [
            'label' => 'Specificity saturation point',
            'help' => 'Only takes effect if specificity-aware relevance weighting is enabled in code. '
                . 'The raw specificity value at which normalized specificity reaches 0.5 — this has no '
                . 'universal correct value, since it depends entirely on this shop\'s own catalog size '
                . 'and vocabulary: use Calibration to sample real values and tune from there. Must be '
                . 'greater than 0.',
            'constraints' => [
                new NotBlank(),
                new GreaterThan(0),
            ],
        ]);

        $builder->add(static::FIELD_SPECIFICITY_WEIGHT_EXPONENT, NumberType::class, [
            'label' => 'Specificity weight exponent',
            'help' => 'Only takes effect if specificity-aware relevance weighting is enabled in code. '
                . 'Reshapes how sharply the specificity-derived shift ramps up as normalized specificity '
                . 'moves away from perfectly average (0.5). 1.0 applies the shift linearly; above 1.0 '
                . 'the shift only grows meaningful once a query is quite unspecific or quite specific; '
                . 'below 1.0 even a mild deviation already produces a fairly large shift. Must be '
                . 'greater than 0.',
            'constraints' => [
                new NotBlank(),
                new GreaterThan(0),
            ],
        ]);

        $builder->add(static::FIELD_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE, NumberType::class, [
            'label' => 'Specificity weight shift magnitude',
            'help' => 'Only takes effect if specificity-aware relevance weighting is enabled in code. The '
                . 'maximum amount the specificity-derived value may shift relevanceWeight away from its '
                . 'configured baseline above, in either direction — a relevanceWeight baseline of exactly '
                . '0.5 reaches the full [0;1] range at 0.5; a baseline nearer either edge reaches '
                . 'correspondingly less on the side nearer that edge, since relevanceWeight itself cannot '
                . 'leave [0;1]. Must be between 0 (specificity has no effect at all) and 1.',
            'constraints' => [
                new NotBlank(),
                new Range(['min' => 0, 'max' => 1]),
            ],
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'search_ranking_settings';
    }
}
