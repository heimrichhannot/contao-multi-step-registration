<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Registration;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;

class EditableMemberFieldProvider
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        $this->framework->initialize();

        System::loadLanguageFile('tl_member');
        $this->framework->getAdapter(Controller::class)->loadDataContainer('tl_member');
        $this->executeOnloadCallbacks();

        $options = [];

        foreach (($GLOBALS['TL_DCA']['tl_member']['fields'] ?? []) as $field => $config) {
            if (!($config['eval']['feEditable'] ?? false)) {
                continue;
            }

            $options[$field] = $GLOBALS['TL_LANG']['tl_member'][$field][0] ?? $field;
        }

        return $options;
    }

    private function executeOnloadCallbacks(): void
    {
        foreach (($GLOBALS['TL_DCA']['tl_member']['config']['onload_callback'] ?? []) as $callback) {
            if (\is_array($callback)) {
                System::importStatic($callback[0])->{$callback[1]}();

                continue;
            }

            if (\is_callable($callback)) {
                $callback();
            }
        }
    }
}
