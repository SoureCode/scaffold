<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Bundle\SettingsBundle\Twig\SettingsExtension;
use SoureCode\Component\Settings\Doctrine\SettingMappingDriver;
use SoureCode\Component\Settings\Manager\DoctrineSettingsManager;
use SoureCode\Component\Settings\Manager\SettingsManagerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(SettingMappingDriver::class)
        ->args([
            '%sourecode.settings.entity_class%',
            '%sourecode.settings.table_name%',
        ]);

    $services->set(DoctrineSettingsManager::class)
        ->args([
            service(EntityManagerInterface::class),
            '%sourecode.settings.entity_class%',
        ]);

    $services->alias(SettingsManagerInterface::class, DoctrineSettingsManager::class);

    $services->set(SettingsExtension::class)
        ->args([service(SettingsManagerInterface::class)])
        ->autoconfigure();
};
