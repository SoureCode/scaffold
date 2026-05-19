<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Versioner;
use SoureCode\Component\Versionable\VersionerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(VersionableMetadataFactory::class);

    $services->set(VersionableListener::class)
        ->args([
            service(VersionableMetadataFactory::class),
            service(ClockInterface::class),
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
        ->tag('doctrine.event_listener', ['event' => 'postFlush']);

    $services->set(VersionableSchemaListener::class)
        ->args([service(VersionableMetadataFactory::class)])
        ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema']);

    $services->set(Versioner::class)
        ->args([
            service(EntityManagerInterface::class),
            service(VersionableMetadataFactory::class),
            service(LoggerInterface::class)->nullOnInvalid(),
        ]);

    $services->alias(VersionerInterface::class, Versioner::class)->public();
};
