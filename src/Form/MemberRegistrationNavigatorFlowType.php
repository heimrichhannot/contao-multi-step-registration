<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Flow\Type\FinishFlowType;
use Symfony\Component\Form\Flow\Type\NextFlowType;
use Symfony\Component\Form\Flow\Type\PreviousFlowType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberRegistrationNavigatorFlowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('previous', PreviousFlowType::class, [
            'clear_submission' => false,
            'validate' => false,
            'validation_groups' => false,
        ]);
        $builder->add('next', NextFlowType::class);
        $builder->add('finish', FinishFlowType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'mapped' => false,
            'priority' => -100,
        ]);
    }
}
