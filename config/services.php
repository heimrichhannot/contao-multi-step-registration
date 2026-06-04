<?php

declare(strict_types=1);

use Contao\CoreBundle\OptIn\OptInInterface;
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
};
