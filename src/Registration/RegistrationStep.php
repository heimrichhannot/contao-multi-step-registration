<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Registration;

final readonly class RegistrationStep
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $fields,
    ) {
    }
}
