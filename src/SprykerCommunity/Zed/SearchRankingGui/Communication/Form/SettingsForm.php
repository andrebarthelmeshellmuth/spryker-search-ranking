<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form;

use Spryker\Zed\Kernel\Communication\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
    public const FIELD_ENTROPY_PROBE_RESULT_SIZE = 'entropyProbeResultSize';

    /**
     * @var string
     */
    public const FIELD_ENTROPY_WEIGHT_EXPONENT = 'entropyWeightExponent';

    /**
     * @var string
     */
    public const FIELD_ENTROPY_WEIGHT_SHIFT_MAGNITUDE = 'entropyWeightShiftMagnitude';

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

        $builder->add(static::FIELD_ENTROPY_PROBE_RESULT_SIZE, IntegerType::class, [
            'label' => 'Entropy probe result size',
            'help' => 'Only takes effect if entropy-aware relevance weighting is enabled in code '
                . '(SearchRankingConfig::isEntropyWeightingEnabled(), off by default). Number of '
                . 'top-ranked candidates the entropy probe samples per search to measure how flat or '
                . 'peaked the text-relevance score distribution is. Must be at least 2 — entropy needs '
                . 'more than one candidate to measure a distribution at all.',
            'constraints' => [
                new NotBlank(),
                new Range(['min' => 2]),
            ],
        ]);

        $builder->add(static::FIELD_ENTROPY_WEIGHT_EXPONENT, NumberType::class, [
            'label' => 'Entropy weight exponent',
            'help' => 'Only takes effect if entropy-aware relevance weighting is enabled in code. '
                . 'Reshapes how sharply the entropy-derived shift ramps up as the probe\'s score '
                . 'distribution moves away from perfectly ambiguous. 1.0 applies the shift linearly; '
                . 'above 1.0 the shift only grows meaningful once the distribution is quite flat or '
                . 'quite peaked; below 1.0 even a mild tilt already produces a fairly large shift. Must '
                . 'be greater than 0.',
            'constraints' => [
                new NotBlank(),
                new GreaterThan(0),
            ],
        ]);

        $builder->add(static::FIELD_ENTROPY_WEIGHT_SHIFT_MAGNITUDE, NumberType::class, [
            'label' => 'Entropy weight shift magnitude',
            'help' => 'Only takes effect if entropy-aware relevance weighting is enabled in code. The '
                . 'maximum amount the entropy-derived value may shift relevanceWeight away from its '
                . 'configured baseline above, in either direction — a relevanceWeight baseline of exactly '
                . '0.5 reaches the full [0;1] range at 0.5; a baseline nearer either edge reaches '
                . 'correspondingly less on the side nearer that edge, since relevanceWeight itself cannot '
                . 'leave [0;1]. Must be between 0 (entropy has no effect at all) and 1.',
            'constraints' => [
                new NotBlank(),
                new Range(['min' => 0, 'max' => 1]),
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
