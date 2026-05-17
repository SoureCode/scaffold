<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ClockInterface::class, Clock::class);

    $services->set(VersionableMetadataFactory::class);

    $services->set(VersionableListener::class)
        ->args([
            service(VersionableMetadataFactory::class),
            service(ClockInterface::class),
        ])
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
        ->tag('doctrine.event_listener', ['event' => 'postFlush']);

    $services->set(VersionableSchemaListener::class)
        ->args([service(VersionableMetadataFactory::class)])
        ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema']);
};
