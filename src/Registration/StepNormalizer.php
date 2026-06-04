<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Registration;

use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class StepNormalizer
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param mixed                 $rawSteps
     * @param array<string, string> $availableFields
     *
     * @return list<RegistrationStep>
     */
    public function normalize(mixed $rawSteps, array $availableFields): array
    {
        $rows = StringUtil::deserialize($rawSteps, true);
        $steps = [];
        $usedKeys = [];

        foreach ($rows as $index => $row) {
            if (!\is_array($row)) {
                continue;
            }

            $fields = array_values(array_unique(array_filter(
                StringUtil::deserialize($row['msrStepFields'] ?? [], true),
                static fn (mixed $field): bool => \is_string($field) && isset($availableFields[$field]),
            )));

            if (!$fields) {
                continue;
            }

            $key = $this->normalizeKey((string) ($row['msrStepKey'] ?? ''), $index);
            $baseKey = $key;
            $suffix = 2;

            while (isset($usedKeys[$key])) {
                $key = $baseKey.'_'.$suffix++;
            }

            $usedKeys[$key] = true;
            $label = trim((string) ($row['msrStepLabel'] ?? ''));

            if ('' === $label) {
                $label = $this->translator->trans('frontend.step', ['%number%' => (string) (\count($steps) + 1)], 'huh_multi_step_registration');
            }

            $steps[] = new RegistrationStep($key, $label, $fields);
        }

        if ($steps) {
            return $steps;
        }

        return [
            new RegistrationStep(
                'registration',
                $this->translator->trans('frontend.registration_step', domain: 'huh_multi_step_registration'),
                array_keys($availableFields),
            ),
        ];
    }

    private function normalizeKey(string $key, int $index): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?: '';
        $key = trim($key, '_');

        return '' !== $key ? $key : 'step_'.($index + 1);
    }
}
