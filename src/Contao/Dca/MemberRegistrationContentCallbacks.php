<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Contao\Dca;

use Contao\Controller;
use Contao\System;

class MemberRegistrationContentCallbacks
{
    /**
     * @return array<string, string>
     */
    public static function getEditableMemberFieldOptions(): array
    {
        System::loadLanguageFile('tl_member');
        Controller::loadDataContainer('tl_member');

        $options = [];

        foreach (($GLOBALS['TL_DCA']['tl_member']['fields'] ?? []) as $field => $config) {
            if ($config['eval']['feEditable'] ?? false) {
                $options[$field] = $GLOBALS['TL_LANG']['tl_member'][$field][0] ?? $field;
            }
        }

        natcasesort($options);

        return $options;
    }
}
