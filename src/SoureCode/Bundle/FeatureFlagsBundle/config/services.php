<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SoureCode\Bundle\FeatureFlagsBundle\Command\FeatureFlagsDisableCommand;
use SoureCode\Bundle\FeatureFlagsBundle\Command\FeatureFlagsEnableCommand;
use SoureCode\Bundle\FeatureFlagsBundle\Command\FeatureFlagsListCommand;
use SoureCode\Bundle\FeatureFlagsBundle\Twig\FeatureFlagsExtension;
use SoureCode\Component\FeatureFlags\Doctrine\FeatureFlagMappingDriver;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactory;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactoryInterface;
use SoureCode\Component\FeatureFlags\Manager\DoctrineFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Manager\EnvOverrideFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(FeatureFlagMappingDriver::class)
        ->args([
            '%sourecode.feature_flags.entity_class%',
            '%sourecode.feature_flags.table_name%',
        ]);

    $services->set(FeatureFlagFactory::class)
        ->args(['%sourecode.feature_flags.entity_class%']);

    $services->alias(FeatureFlagFactoryInterface::class, FeatureFlagFactory::class);

    $services->set(DoctrineFeatureFlagsManager::class)
        ->args([
            service(EntityManagerInterface::class),
            '%sourecode.feature_flags.entity_class%',
            service(FeatureFlagFactoryInterface::class),
            service(EventDispatcherInterface::class)->nullOnInvalid(),
        ]);

    $services->set(EnvOverrideFeatureFlagsManager::class)
        ->args([
            service(DoctrineFeatureFlagsManager::class),
            '%sourecode.feature_flags.env_override.prefix%',
        ]);

    $services->alias(FeatureFlagsManagerInterface::class, DoctrineFeatureFlagsManager::class);

    $services->set(FeatureFlagsExtension::class)
        ->args([service(FeatureFlagsManagerInterface::class)])
        ->autoconfigure();

    $services->set(FeatureFlagsListCommand::class)
        ->args([service(FeatureFlagsManagerInterface::class)])
        ->tag('console.command');

    $services->set(FeatureFlagsEnableCommand::class)
        ->args([service(FeatureFlagsManagerInterface::class)])
        ->tag('console.command');

    $services->set(FeatureFlagsDisableCommand::class)
        ->args([service(FeatureFlagsManagerInterface::class)])
        ->tag('console.command');
};
