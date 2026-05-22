<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Lifecycle\Clock\TimestampFactory;
use SoureCode\Component\Lifecycle\EventListener\TimestampableListener;
use SoureCode\Component\Lifecycle\EventListener\TimestampableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\TimestampableMetadataFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TimestampableMetadataFactory::class);
    $services->set(TimestampFactory::class)
        ->args([service(ClockInterface::class)]);

    // Listener tags are owned by LifecycleBundle::loadExtension via
    // PrioritizedListenerRegistrar so listener priorities have one
    // source of truth.

    $services->set(TimestampableListener::class)
        ->args([
            service(TimestampableMetadataFactory::class),
            service(TimestampFactory::class),
            service(ChangeSetMatcher::class),
        ]);

    $services->set(TimestampableMappingListener::class)
        ->args([service(TimestampableMetadataFactory::class)]);
};
