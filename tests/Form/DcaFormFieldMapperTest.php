<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Tests\Form;

use Doctrine\DBAL\Connection;
use HeimrichHannot\MultiStepRegistration\Form\DcaFormFieldMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class DcaFormFieldMapperTest extends TestCase
{
    public function testItMapsEmailFieldsAndConstraints(): void
    {
        $mapper = new DcaFormFieldMapper($this->createMock(Connection::class));
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
        self::assertContainsOnlyInstancesOf(Email::class, [$constraints[1]]);
        self::assertContainsOnlyInstancesOf(Length::class, [$constraints[2]]);
        self::assertSame(255, $options['attr']['maxlength']);
    }

    public function testItMapsPasswordToRepeatedType(): void
    {
        $mapper = new DcaFormFieldMapper($this->createMock(Connection::class));
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
        $mapper = new DcaFormFieldMapper($this->createMock(Connection::class));
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

    public function testItSerializesMultipleValuesWithoutCsv(): void
    {
        $mapper = new DcaFormFieldMapper($this->createMock(Connection::class));
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
}
