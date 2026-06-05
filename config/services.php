<?php

declare(strict_types=1);

use Contao\CoreBundle\OptIn\OptInInterface;
use HeimrichHannot\MultiStepRegistration\Registration\MemberFieldTagsManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services
        ->load('HeimrichHannot\\MultiStepRegistration\\', '../src/')
    ;

    $services
        ->alias(OptInInterface::class, 'contao.opt_in')
    ;

    $services
        ->set(MemberFieldTagsManager::class)
        ->tag('codefog_tags.manager', ['alias' => 'huh_msr_member_fields'])
    ;
};
