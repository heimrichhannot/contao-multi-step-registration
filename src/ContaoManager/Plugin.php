<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\ContaoManager;

use Codefog\TagsBundle\CodefogTagsBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use HeimrichHannot\MultiStepRegistration\HeimrichHannotMultiStepRegistrationBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(HeimrichHannotMultiStepRegistrationBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class, CodefogTagsBundle::class]),
        ];
    }
}
