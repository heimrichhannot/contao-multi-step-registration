<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Tests\Registration;

use HeimrichHannot\MultiStepRegistration\Registration\StepNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class StepNormalizerTest extends TestCase
{
    public function testItNormalizesConfiguredSteps(): void
    {
        $normalizer = new StepNormalizer(new Translator('en'));
        $steps = $normalizer->normalize([
            ['msrStepKey' => 'Contact data', 'msrStepLabel' => 'Contact', 'msrStepFields' => ['email', 'firstname']],
            ['msrStepKey' => 'Contact data', 'msrStepLabel' => 'Profile', 'msrStepFields' => ['lastname', 'unknown']],
        ], [
            'email' => 'Email',
            'firstname' => 'Firstname',
            'lastname' => 'Lastname',
        ]);

        self::assertCount(2, $steps);
        self::assertSame('contact_data', $steps[0]->key);
        self::assertSame('contact_data_2', $steps[1]->key);
        self::assertSame(['lastname'], $steps[1]->fields);
    }

    public function testItFallsBackToOneStepWithAllAvailableFields(): void
    {
        $normalizer = new StepNormalizer(new Translator('en'));
        $steps = $normalizer->normalize([], ['email' => 'Email', 'firstname' => 'Firstname']);

        self::assertCount(1, $steps);
        self::assertSame('registration', $steps[0]->key);
        self::assertSame(['email', 'firstname'], $steps[0]->fields);
    }
}
