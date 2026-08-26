<?php

return [
    'tl_content' => [
        'msr_steps_legend' => 'Registration steps',
        'msr_config_legend' => 'Registration settings',
        'msr_home_dir_legend' => 'Home directory',
        'msr_activation_legend' => 'Activation',
        'msrSteps' => [
            0 => 'Steps',
            1 => 'Configure the registration steps and the member fields shown in each step.',
        ],
        'msrStepKey' => [
            0 => 'Step key',
            1 => 'Optional technical key. It must be unique within this content element.',
        ],
        'msrStepLabel' => [
            0 => 'Step label',
            1 => 'Optional visible label for this step.',
        ],
        'msrStepFields' => [
            0 => 'Member fields',
            1 => 'Choose the editable member fields shown in this step.',
        ],
        'msrDisableCaptcha' => [
            0 => 'Disable security question',
            1 => 'Do not show the Contao security question.',
        ],
        'msrGroups' => [
            0 => 'Member groups',
            1 => 'Assign new members to these groups.',
        ],
        'msrAllowLogin' => [
            0 => 'Allow login',
            1 => 'Allow the new member to log in after activation.',
        ],
        'msrAssignDir' => [
            0 => 'Assign a home directory',
            1 => 'Create and assign a personal directory for each new member.',
        ],
        'msrHomeDir' => [
            0 => 'Home directory',
            1 => 'Choose the parent directory for member directories.',
        ],
        'msrActivate' => [
            0 => 'Send activation link',
            1 => 'Send an activation email before the member account can be used.',
        ],
        'msrRegJumpTo' => [
            0 => 'Activation target page',
            1 => 'Redirect to this page after successful activation.',
        ],
        'msrRegText' => [
            0 => 'Activation email text',
            1 => 'Use simple tokens such as ##link## and ##activation##.',
        ],
        'msrJumpTo' => [
            0 => 'Redirect page after submitting the form',
            1 => 'Redirect to this page after successful registration.',
        ],
    ],
];
