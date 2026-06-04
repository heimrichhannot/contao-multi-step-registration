<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Form;

use HeimrichHannot\MultiStepRegistration\Registration\RegistrationStep;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberRegistrationFlowType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        foreach ($options['steps'] as $step) {
            $builder->addStep($step->key, MemberRegistrationStepType::class, [
                'fields' => $step->fields,
                'step_label' => $step->label,
                'dca_fields' => $options['dca_fields'],
                'constraints_by_field' => $options['constraints_by_field'],
                'attr_by_field' => $options['attr_by_field'],
            ]);
        }

        $builder->add('navigator', NavigatorFlowType::class, [
            'translation_domain' => 'huh_multi_step_registration',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'auto_reset' => false,
        ]);

        $resolver->setRequired(['steps', 'dca_fields', 'constraints_by_field', 'attr_by_field']);
        $resolver->setAllowedTypes('steps', 'array');
        $resolver->setAllowedTypes('dca_fields', 'array');
        $resolver->setAllowedTypes('constraints_by_field', 'array');
        $resolver->setAllowedTypes('attr_by_field', 'array');
        $resolver->setAllowedValues('steps', static fn (array $steps): bool => [] !== $steps && array_reduce($steps, static fn (bool $valid, mixed $step): bool => $valid && $step instanceof RegistrationStep, true));
    }
}
