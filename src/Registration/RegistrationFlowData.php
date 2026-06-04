<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Registration;

final class RegistrationFlowData
{
    public ?string $currentStep = null;

    /**
     * @var array<string, mixed>
     */
    public array $values = [];
}
