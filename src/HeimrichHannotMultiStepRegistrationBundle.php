<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class HeimrichHannotMultiStepRegistrationBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ('' === $this->extensionAlias) {
            $this->extensionAlias = 'huh_multi_step_registration';
        }

        return parent::getContainerExtension();
    }
}
