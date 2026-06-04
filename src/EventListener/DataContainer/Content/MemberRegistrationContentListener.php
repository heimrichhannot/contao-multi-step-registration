<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\EventListener\DataContainer\Content;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\System;

class MemberRegistrationContentListener
{
    /**
     * @return array<string, string>
     */
    #[AsCallback(table: 'tl_content', target: 'fields.msrSteps.fields.msrStepFields.options')]
    public function getEditableMemberFieldOptions(?DataContainer $dc = null): array
    {
        System::loadLanguageFile('tl_member');
        Controller::loadDataContainer('tl_member');

        $options = [];

        foreach (($GLOBALS['TL_DCA']['tl_member']['fields'] ?? []) as $field => $config) {
            if ($config['eval']['feEditable'] ?? false) {
                $options[$field] = $GLOBALS['TL_LANG']['tl_member'][$field][0] ?? $field;
            }
        }

        return $options;
    }
}
