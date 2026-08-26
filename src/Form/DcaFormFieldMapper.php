<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Form;

use Contao\Date;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DcaFormFieldMapper
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $dca
     * @param list<object>         $constraints
     * @param array<string, mixed> $attr
     *
     * @return array{0: class-string, 1: array<string, mixed>}
     */
    public function mapField(string $field, array $dca, array $constraints, array $attr): array
    {
        $eval = $dca['eval'] ?? [];
        $inputType = $dca['inputType'] ?? 'text';
        $type = $this->mapType($inputType, $eval);
        $options = [
            'label' => $dca['label'][0] ?? $GLOBALS['TL_LANG']['tl_member'][$field][0] ?? $field,
            'help' => $dca['label'][1] ?? $GLOBALS['TL_LANG']['tl_member'][$field][1] ?? null,
            'required' => (bool) ($eval['mandatory'] ?? false),
            'property_path' => 'values['.$field.']',
            'constraints' => $constraints,
            'attr' => $attr,
        ];

        if (\array_key_exists('default', $dca)) {
            $options['empty_data'] = $dca['default'];
        }

        if (ChoiceType::class === $type) {
            $options['choices'] = $this->getChoices($dca);
            $options['multiple'] = (bool) ($eval['multiple'] ?? false);
            $options['expanded'] = \in_array($inputType, ['checkbox', 'radio'], true);
            $options['placeholder'] = ($eval['includeBlankOption'] ?? false) ? '' : null;
        }

        if (CheckboxType::class === $type) {
            $options['required'] = false;
        }

        if (DateType::class === $type) {
            $options['widget'] = 'single_text';
            $options['html5'] = true;
        }

        if ('password' === $inputType) {
            $options['type'] = PasswordType::class;
            $options['invalid_message'] = $GLOBALS['TL_LANG']['ERR']['passwordMatch'] ?? 'The passwords do not match.';
            $options['first_options'] = ['label' => $options['label']];
            $options['second_options'] = ['label' => $GLOBALS['TL_LANG']['MSC']['confirm'][0] ?? 'Confirm password'];
        }

        return [$type, $options];
    }

    /**
     * @param array<string, mixed> $dca
     *
     * @return list<object>
     */
    public function createConstraints(string $field, array $dca): array
    {
        $eval = $dca['eval'] ?? [];
        $constraints = [];

        if ($eval['mandatory'] ?? false) {
            $constraints[] = new NotBlank();
        }

        $length = [];

        if (isset($eval['minlength'])) {
            $length['min'] = (int) $eval['minlength'];
        }

        if (isset($eval['maxlength']) && (int) $eval['maxlength'] > 0) {
            $length['max'] = (int) $eval['maxlength'];
        }

        if ($length) {
            $constraints[] = new Length($length);
        }

        $rgxp = $eval['rgxp'] ?? null;

        if ('email' === $rgxp) {
            $constraints[] = new Email();
        } elseif ('url' === $rgxp) {
            $constraints[] = new Regex(['pattern' => '~^https?://|^/|^{{~i', 'message' => $GLOBALS['TL_LANG']['ERR']['url'] ?? 'Please enter a valid URL.']);
        } elseif ('phone' === $rgxp) {
            $constraints[] = new Regex(['pattern' => '~^[0-9+()./ -]*$~']);
        }

        if ($eval['unique'] ?? false) {
            $constraints[] = new Callback(function (mixed $value, ExecutionContextInterface $context) use ($field, $dca): void {
                if (\is_array($value) || '' === (string) $value) {
                    return;
                }

                $exists = (bool) $this->connection->fetchOne('SELECT 1 FROM tl_member WHERE '.$this->connection->quoteIdentifier($field).' = ? LIMIT 1', [$value]);

                if ($exists) {
                    $context->buildViolation($GLOBALS['TL_LANG']['ERR']['unique'] ?? 'This value already exists.')
                        ->setParameter('%s', $dca['label'][0] ?? $field)
                        ->addViolation();
                }
            });
        }

        return $constraints;
    }

    /**
     * @param array<string, mixed> $dca
     *
     * @return array<string, mixed>
     */
    public function createAttributes(array $dca): array
    {
        $eval = $dca['eval'] ?? [];
        $attr = [];

        if (isset($eval['maxlength']) && (int) $eval['maxlength'] > 0) {
            $attr['maxlength'] = (int) $eval['maxlength'];
        }

        if (isset($eval['placeholder'])) {
            $attr['placeholder'] = $eval['placeholder'];
        }

        return $attr;
    }

    /**
     * @param array<string, mixed> $dcaFields
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function normalizeSubmittedValues(array $dcaFields, array $values): array
    {
        $normalized = [];

        foreach ($values as $field => $value) {
            $dca = $dcaFields[$field] ?? null;

            if (!\is_array($dca)) {
                continue;
            }

            $normalized[$field] = $this->normalizeValue($field, $dca, $value);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $dca
     */
    private function normalizeValue(string $field, array $dca, mixed $value): mixed
    {
        $eval = $dca['eval'] ?? [];

        if ('' === $value) {
            $value = ($dca['inputType'] ?? null) === 'checkbox' ? '' : null;
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->getTimestamp();
        }

        if ('password' === ($dca['inputType'] ?? null) && \is_string($value) && '' !== $value) {
            $value = $this->passwordHasherFactory
                ->getPasswordHasher(FrontendUser::class)
                ->hash($value);
        }

        if (null !== $value && '' !== $value && \in_array($eval['rgxp'] ?? null, ['date', 'time', 'datim'], true)) {
            /** @phpstan-ignore argument.type */
            $value = (new Date((string) $value, Date::getFormatFromRgxp((string) $eval['rgxp'])))->tstamp;
        }

        if (($eval['multiple'] ?? false) && \is_array($value)) {
            if (isset($eval['csv'])) {
                $value = implode((string) $eval['csv'], $value);
            } else {
                ksort($value);
                $value = serialize($value);
            }
        }

        foreach (($dca['save_callback'] ?? []) as $callback) {
            if (\is_array($callback)) {
                $value = System::importStatic($callback[0])->{$callback[1]}($value, null);

                continue;
            }

            if (\is_callable($callback)) {
                $value = $callback($value, null);
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $eval
     *
     * @return class-string
     */
    private function mapType(string $inputType, array $eval): string
    {
        if ('email' === ($eval['rgxp'] ?? null)) {
            return EmailType::class;
        }

        if ('url' === ($eval['rgxp'] ?? null)) {
            return UrlType::class;
        }

        return match ($inputType) {
            'textarea' => TextareaType::class,
            'select', 'radio', 'checkboxWizard' => ChoiceType::class,
            'checkbox' => ($eval['multiple'] ?? false) ? ChoiceType::class : CheckboxType::class,
            'password' => RepeatedType::class,
            'hidden' => HiddenType::class,
            default => \in_array($eval['rgxp'] ?? null, ['date', 'time', 'datim'], true) ? DateType::class : TextType::class,
        };
    }

    /**
     * @param array<string, mixed> $dca
     *
     * @return array<string, string>
     */
    private function getChoices(array $dca): array
    {
        $options = $dca['options'] ?? [];

        if (isset($dca['options_callback'])) {
            $options = $this->executeOptionsCallback($dca['options_callback']);
        }

        $reference = $dca['reference'] ?? [];
        $choices = [];
        $options = StringUtil::deserialize($options, true);
        $isList = array_is_list($options);

        foreach ($options as $key => $value) {
            $choiceValue = $isList ? (string) $value : (string) $key;
            $label = $this->getChoiceLabel($choiceValue, (string) $value, $reference);
            $choices[(string) $label] = $choiceValue;
        }

        return $choices;
    }

    /**
     * @param array<string, mixed> $reference
     */
    private function getChoiceLabel(string $choiceValue, string $fallback, array $reference): string
    {
        $label = $reference[$choiceValue] ?? null;

        if (\is_array($label)) {
            return (string) ($label[0] ?? $fallback);
        }

        if (null !== $label) {
            return (string) $label;
        }

        return (string) ($GLOBALS['TL_LANG']['MSC'][$choiceValue] ?? $fallback);
    }

    private function executeOptionsCallback(mixed $callback): mixed
    {
        if (\is_array($callback)) {
            return System::importStatic($callback[0])->{$callback[1]}();
        }

        if (\is_callable($callback)) {
            return $callback();
        }

        return [];
    }
}
