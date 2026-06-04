<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberRegistrationStepType extends AbstractType
{
    public function __construct(private readonly DcaFormFieldMapper $fieldMapper)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options['fields'] as $field) {
            $config = $options['dca_fields'][$field] ?? null;

            if (!\is_array($config)) {
                continue;
            }

            [$type, $fieldOptions] = $this->fieldMapper->mapField(
                $field,
                $config,
                $options['constraints_by_field'][$field] ?? [],
                $options['attr_by_field'][$field] ?? [],
            );

            $builder->add($field, $type, $fieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
            'label' => false,
        ]);

        $resolver->setRequired(['fields', 'step_label', 'dca_fields', 'constraints_by_field', 'attr_by_field']);
        $resolver->setAllowedTypes('fields', 'array');
        $resolver->setAllowedTypes('step_label', 'string');
        $resolver->setAllowedTypes('dca_fields', 'array');
        $resolver->setAllowedTypes('constraints_by_field', 'array');
        $resolver->setAllowedTypes('attr_by_field', 'array');
    }
}
