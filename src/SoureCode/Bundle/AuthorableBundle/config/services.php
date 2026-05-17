<?php

declare(strict_types=1);

use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\EventListener\AuthorableListener;
use SoureCode\Component\Authorable\EventListener\AuthorableMappingListener;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(AuthorableMetadataFactory::class);

    $services->set(AuthorableListener::class)
        ->args([
            service(AuthorProviderInterface::class),
            service(AuthorableMetadataFactory::class),
            service(ChangeSetMatcher::class),
        ])
        ->tag('doctrine.event_listener', ['event' => 'prePersist'])
        ->tag('doctrine.event_listener', ['event' => 'onFlush']);

    $services->set(AuthorableMappingListener::class)
        ->args([service(AuthorableMetadataFactory::class)])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
};
