<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Form;

use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Spryker\Zed\Kernel\Communication\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class MetricForm extends AbstractType
{
    /**
     * @var string
     */
    public const OPTION_SEARCH_RANKING_FACADE = 'option_search_ranking_facade';

    /**
     * @var string
     */
    protected const FIELD_NAME = 'name';

    /**
     * @var string
     */
    protected const FIELD_WEIGHT = 'weight';

    /**
     * @var string
     */
    protected const FIELD_FORMULA = 'formula';

    /**
     * @var string
     */
    protected const FIELD_IS_ACTIVE = 'isActive';

    /**
     * @var string
     */
    protected const FIELD_IS_HIGHER_BETTER = 'isHigherBetter';

    /**
     * @var string
     */
    protected const FIELD_AUTO_TUNE_THRESHOLD = 'autoTuneThreshold';

    /**
     * @var string
     */
    protected const FIELD_IS_AUTO_UPDATE_ENABLED = 'isAutoUpdateEnabled';

    /**
     * @var string
     */
    protected const FIELD_IS_AUTO_TUNE_NOTIFY_ENABLED = 'isAutoTuneNotifyEnabled';

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolver $resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchRankingMetricTransfer::class,
        ]);

        $resolver->setRequired(static::OPTION_SEARCH_RANKING_FACADE);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this
            ->addNameField($builder, $options)
            ->addWeightField($builder)
            ->addIsHigherBetterField($builder)
            ->addFormulaField($builder, $options)
            ->addIsActiveField($builder)
            ->addAutoTuneThresholdField($builder)
            ->addIsAutoUpdateEnabledField($builder)
            ->addIsAutoTuneNotifyEnabledField($builder);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
     *
     * @return $this
     */
    protected function addNameField(FormBuilderInterface $builder, array $options)
    {
        /** @var \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface $searchRankingFacade */
        $searchRankingFacade = $options[static::OPTION_SEARCH_RANKING_FACADE];

        $builder->add(static::FIELD_NAME, TextType::class, [
            'label' => 'Name',
            'help' => 'Machine name of the business signal, e.g. pdp_impressions. Lowercase letters, digits and underscores.',
            'constraints' => [
                new NotBlank(),
                new Length(['max' => 128]),
                new Regex([
                    'pattern' => '/^[a-z][a-z0-9_]*$/',
                    'message' => 'Use lowercase letters, digits and underscores; start with a letter.',
                ]),
                new Callback([
                    'callback' => function (?string $name, ExecutionContextInterface $context) use ($searchRankingFacade): void {
                        if (!$name) {
                            return;
                        }

                        $existingMetricTransfer = $searchRankingFacade->findMetricByName($name);

                        if ($existingMetricTransfer === null) {
                            return;
                        }

                        /** @var \Generated\Shared\Transfer\SearchRankingMetricTransfer $formData */
                        $formData = $context->getRoot()->getData();

                        if ($existingMetricTransfer->getIdSearchRankingMetric() === $formData->getIdSearchRankingMetric()) {
                            return;
                        }

                        $context->addViolation('A metric with this name already exists.');
                    },
                ]),
            ],
        ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addWeightField(FormBuilderInterface $builder)
    {
        $builder->add(static::FIELD_WEIGHT, NumberType::class, [
            'label' => 'Weight',
            'help' => 'Contribution of this metric to the combined ranking score.',
            'constraints' => [
                new NotBlank(),
                new GreaterThanOrEqual(0),
            ],
        ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addIsHigherBetterField(FormBuilderInterface $builder)
    {
        $builder->add(static::FIELD_IS_HIGHER_BETTER, CheckboxType::class, [
            'label' => 'Higher raw value is better',
            'help' => 'Checked for signals like sales or impressions (more is better). Unchecked for signals like days-since-restock or return rate (less is better) — this only affects the curve-fit suggestions below, never the formula itself.',
            'required' => false,
        ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
     *
     * @return $this
     */
    protected function addFormulaField(FormBuilderInterface $builder, array $options)
    {
        /** @var \SprykerCommunity\Zed\SearchRankingGui\Dependency\Facade\SearchRankingGuiToSearchRankingFacadeInterface $searchRankingFacade */
        $searchRankingFacade = $options[static::OPTION_SEARCH_RANKING_FACADE];

        $builder->add(static::FIELD_FORMULA, TextType::class, [
            'label' => 'Normalization formula',
            'help' => 'Expression mapping raw values into ]0;1]. Variables: x, min, max, avg, count. Functions: atan, tanh, log, log10, sqrt, exp, abs, pi, pow, round, min, max, random. Example: atan(x / avg) / (pi() / 2)',
            'constraints' => [
                new NotBlank(),
                new Length(['max' => 512]),
                new Callback([
                    'callback' => function (?string $formula, ExecutionContextInterface $context) use ($searchRankingFacade): void {
                        if (!$formula) {
                            return;
                        }

                        $validationResponseTransfer = $searchRankingFacade->validateFormula($formula);

                        if ($validationResponseTransfer->getIsSuccess()) {
                            return;
                        }

                        $context->addViolation('Invalid formula: {{ error }}', [
                            '{{ error }}' => (string)$validationResponseTransfer->getErrorMessage(),
                        ]);
                    },
                ]),
            ],
        ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addIsActiveField(FormBuilderInterface $builder)
    {
        $builder->add(static::FIELD_IS_ACTIVE, CheckboxType::class, [
            'label' => 'Active',
            'required' => false,
        ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addAutoTuneThresholdField(FormBuilderInterface $builder)
    {
        $builder->add(static::FIELD_AUTO_TUNE_THRESHOLD, NumberType::class, [
            'label' => 'Auto-tune fit threshold (R²)',
            'help' => 'Leave empty to keep this metric out of search-ranking:auto-tune entirely. When set, the monthly job flags this metric whenever its live formula\'s fit against the current data drops below this R² value (0 to 1).',
            'required' => false,
            'constraints' => [
                new Range(['min' => 0, 'max' => 1]),
            ],
        ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addIsAutoUpdateEnabledField(FormBuilderInterface $builder)
    {
        $builder->add(static::FIELD_IS_AUTO_UPDATE_ENABLED, CheckboxType::class, [
            'label' => 'Auto-update on drift',
            'help' => 'When the fit drops below the threshold above, automatically apply the best-fitting refit (same validation and history logging as a manual edit) and republish. Ignored while no threshold is set.',
            'required' => false,
        ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addIsAutoTuneNotifyEnabledField(FormBuilderInterface $builder)
    {
        $builder->add(static::FIELD_IS_AUTO_TUNE_NOTIFY_ENABLED, CheckboxType::class, [
            'label' => 'Notify on drift',
            'help' => 'When the fit drops below the threshold above, email every admin holding the auto-tune notification ACL role a before/after summary — independently of the auto-update setting above (a proposed-changes notice if auto-update is off, an applied-changes summary if it is on). Ignored while no threshold is set.',
            'required' => false,
        ]);

        return $this;
    }

    /**
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'search_ranking_metric';
    }
}
