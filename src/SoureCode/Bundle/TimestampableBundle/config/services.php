<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\Timestampable\EventListener\TimestampableListener;
use SoureCode\Component\Timestampable\EventListener\TimestampableMappingListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TimestampableMetadataFactory::class);
    $services->set(TimestampFactory::class)
        ->args([service(ClockInterface::class)]);

    $services->set(TimestampableListener::class)
        ->args([
            service(TimestampableMetadataFactory::class),
            service(TimestampFactory::class),
            service(ChangeSetMatcher::class),
        ])
        ->tag('doctrine.event_listener', ['event' => 'prePersist'])
        ->tag('doctrine.event_listener', ['event' => 'onFlush']);

    $services->set(TimestampableMappingListener::class)
        ->args([service(TimestampableMetadataFactory::class)])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
};
