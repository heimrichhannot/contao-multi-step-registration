<?php

declare(strict_types=1);

use HeimrichHannot\MultiStepRegistration\Contao\Dca\MemberRegistrationContentCallbacks;
use HeimrichHannot\MultiStepRegistration\Controller\ContentElement\MultiStepRegistrationElementController;

$dca = &$GLOBALS['TL_DCA']['tl_content'];

$dca['palettes'][MultiStepRegistrationElementController::TYPE] = '
    {type_legend},type,headline;
    {msr_steps_legend},msrSteps;
    {msr_config_legend},msrDisableCaptcha,msrGroups,msrAllowLogin;
    {msr_home_dir_legend:collapsed},msrAssignDir;
    {msr_activation_legend:collapsed},msrActivate,msrRegJumpTo,msrRegText;
    {redirect_legend:collapsed},msrJumpTo;
    {template_legend:collapsed},customTpl;
    {protected_legend:collapsed},protected;
    {expert_legend:collapsed},guests,invisible,start,stop,cssID,cssClass
';

$dca['palettes']['__selector__'][] = 'msrAssignDir';
$dca['subpalettes']['msrAssignDir'] = 'msrHomeDir';

$dca['fields']['msrSteps'] = [
    'exclude' => true,
    'inputType' => 'rowWizard',
    'fields' => [
        'msrStepKey' => [
            'label' => &$GLOBALS['TL_LANG']['tl_content']['msrStepKey'],
            'inputType' => 'text',
            'eval' => ['maxlength' => 64, 'rgxp' => 'alias', 'style' => 'width:160px'],
        ],
        'msrStepLabel' => [
            'label' => &$GLOBALS['TL_LANG']['tl_content']['msrStepLabel'],
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'style' => 'width:220px'],
        ],
        'msrStepFields' => [
            'label' => &$GLOBALS['TL_LANG']['tl_content']['msrStepFields'],
            'inputType' => 'checkbox',
            'options_callback' => [MemberRegistrationContentCallbacks::class, 'getEditableMemberFieldOptions'],
            'eval' => ['multiple' => true, 'mandatory' => true],
        ],
    ],
    'eval' => ['tl_class' => 'clr', 'mandatory' => true],
    'sql' => 'text NULL',
];

$dca['fields']['msrDisableCaptcha'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
];

$dca['fields']['msrGroups'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'foreignKey' => 'tl_member_group.name',
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
];

$dca['fields']['msrAllowLogin'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
];

$dca['fields']['msrAssignDir'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'w50'],
];

$dca['fields']['msrHomeDir'] = [
    'exclude' => true,
    'inputType' => 'fileTree',
    'eval' => ['fieldType' => 'radio', 'files' => false, 'mandatory' => true, 'tl_class' => 'clr'],
];

$dca['fields']['msrActivate'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
];

$dca['fields']['msrRegJumpTo'] = [
    'exclude' => true,
    'inputType' => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
];

$dca['fields']['msrRegText'] = [
    'exclude' => true,
    'inputType' => 'textarea',
    'eval' => ['rte' => 'tinyMCE', 'helpwizard' => true, 'tl_class' => 'clr'],
    'explanation' => 'insertTags',
];

$dca['fields']['msrJumpTo'] = [
    'exclude' => true,
    'inputType' => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
];
