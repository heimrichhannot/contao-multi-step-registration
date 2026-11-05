<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Tests\Form;

use Doctrine\DBAL\Connection;
use HeimrichHannot\MultiStepRegistration\Form\DcaFormFieldMapper;
use HeimrichHannot\MultiStepRegistration\Form\MemberRegistrationStepType;
use HeimrichHannot\MultiStepRegistration\Registration\RegistrationFlowData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

class DcaFormFieldMapperTest extends TestCase
{
    public function testItMapsEmailFieldsAndConstraints(): void
    {
        $mapper = $this->createMapper();
        $dca = [
            'inputType' => 'text',
            'label' => ['Email', 'Your email address'],
            'eval' => ['mandatory' => true, 'rgxp' => 'email', 'maxlength' => 255],
        ];

        $constraints = $mapper->createConstraints('email', $dca);
        [$type, $options] = $mapper->mapField('email', $dca, $constraints, $mapper->createAttributes($dca));

        self::assertSame(EmailType::class, $type);
        self::assertSame('values[email]', $options['property_path']);
        self::assertContainsOnlyInstancesOf(NotBlank::class, [$constraints[0]]);
        self::assertContainsOnlyInstancesOf(Length::class, [$constraints[1]]);
        self::assertContainsOnlyInstancesOf(Email::class, [$constraints[2]]);
        self::assertSame(255, $options['attr']['maxlength']);
    }

    public function testItMapsPasswordToRepeatedType(): void
    {
        $mapper = $this->createMapper();
        [$type, $options] = $mapper->mapField('password', [
            'inputType' => 'password',
            'label' => ['Password', ''],
            'eval' => ['mandatory' => true],
        ], [], []);

        self::assertSame(RepeatedType::class, $type);
        self::assertSame('values[password]', $options['property_path']);
    }

    public function testItPreservesAssociativeNumericChoiceKeys(): void
    {
        $mapper = $this->createMapper();
        [$type, $options] = $mapper->mapField('area', [
            'inputType' => 'select',
            'label' => ['Area', ''],
            'options' => [
                14 => 'Dresden',
                22 => 'Leipzig',
                'others' => 'Other',
            ],
            'eval' => ['multiple' => true],
        ], [], []);

        self::assertSame(ChoiceType::class, $type);
        self::assertTrue($options['multiple']);
        self::assertSame([
            'Dresden' => '14',
            'Leipzig' => '22',
            'Other' => 'others',
        ], $options['choices']);
    }

    public function testItMapsStringReferenceLabelsWithoutTruncatingThem(): void
    {
        $mapper = $this->createMapper();
        [$type, $options] = $mapper->mapField('type', [
            'inputType' => 'select',
            'label' => ['Member type', ''],
            'options' => ['provider', 'initiative', 'default'],
            'reference' => [
                'provider' => 'Provider',
                'initiative' => 'Initiative',
                'default' => 'Other',
            ],
        ], [], []);

        self::assertSame(ChoiceType::class, $type);
        self::assertSame([
            'Provider' => 'provider',
            'Initiative' => 'initiative',
            'Other' => 'default',
        ], $options['choices']);
    }

    public function testItSerializesMultipleValuesWithoutCsv(): void
    {
        $mapper = $this->createMapper();
        $values = $mapper->normalizeSubmittedValues([
            'area' => [
                'inputType' => 'select',
                'eval' => ['multiple' => true],
            ],
        ], [
            'area' => ['22', '14', 'others'],
        ]);

        self::assertSame(serialize(['22', '14', 'others']), $values['area']);
    }

    public function testItHashesPasswordsWithTheInjectedHasherFactory(): void
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher
            ->expects(self::once())
            ->method('hash')
            ->with('secret')
            ->willReturn('hashed-password')
        ;

        $hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory
            ->expects(self::once())
            ->method('getPasswordHasher')
            ->with(\Contao\FrontendUser::class)
            ->willReturn($hasher)
        ;

        $mapper = new DcaFormFieldMapper($this->createMock(Connection::class), $hasherFactory);
        $values = $mapper->normalizeSubmittedValues([
            'password' => ['inputType' => 'password'],
        ], [
            'password' => 'secret',
        ]);

        self::assertSame('hashed-password', $values['password']);
    }

    #[DataProvider('checkboxCases')]
    public function testCheckboxRequirementAndSubmission(?bool $mandatory, bool $checked): void
    {
        $mapper = $this->createMapper();
        $dca = [
            'inputType' => 'checkbox',
            'eval' => $mandatory === null ? [] : ['mandatory' => $mandatory],
        ];
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()))
            ->addType(new MemberRegistrationStepType($mapper))
            ->getFormFactory();
        $data = new RegistrationFlowData();
        $form = $factory->createBuilder(FormType::class, $data)
            ->add('login', MemberRegistrationStepType::class, [
                'fields' => ['terms'],
                'step_label' => 'Login',
                'dca_fields' => ['terms' => $dca],
                'constraints_by_field' => ['terms' => $mapper->createConstraints('terms', $dca)],
                'attr_by_field' => [],
            ])
            ->getForm();

        $form->submit(['login' => $checked ? ['terms' => '1'] : []]);

        self::assertSame(!$mandatory || $checked, $form->isValid());
        self::assertSame($checked, $data->values['terms']);
        $view = $form->createView()['login']['terms'];
        self::assertSame($checked, $view->vars['checked']);
        self::assertSame((bool) $mandatory, $view->vars['required']);
    }

    public static function checkboxCases(): iterable
    {
        yield 'mandatory unchecked' => [true, false];
        yield 'mandatory checked' => [true, true];
        yield 'optional unchecked' => [false, false];
        yield 'optional checked' => [false, true];
        yield 'unspecified unchecked' => [null, false];
        yield 'unspecified checked' => [null, true];
    }

    private function createMapper(): DcaFormFieldMapper
    {
        return new DcaFormFieldMapper(
            $this->createMock(Connection::class),
            $this->createMock(PasswordHasherFactoryInterface::class),
        );
    }
}
