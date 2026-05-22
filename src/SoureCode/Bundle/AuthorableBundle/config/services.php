<?php

declare(strict_types=1);

use SoureCode\Bundle\AuthorableBundle\Security\SecurityAuthorProvider;
use SoureCode\Bundle\AuthorableBundle\Security\SecurityImpersonatorProvider;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\Author\ImpersonatorProviderInterface;
use SoureCode\Component\Authorable\EventListener\AuthorableListener;
use SoureCode\Component\Authorable\EventListener\AuthorableMappingListener;
use SoureCode\Component\Authorable\EventListener\ImpersonatorListener;
use SoureCode\Component\Authorable\Removable\AuthorableDeletionMarkerProvider;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->instanceof(AuthorProviderInterface::class)
        ->tag('sourecode.authorable.author_provider');

    $services->set(AuthorableMetadataFactory::class);

    $services->set(SecurityAuthorProvider::class)
        ->args([service(Security::class)]);

    $services->set(SecurityImpersonatorProvider::class)
        ->args([service(Security::class)->nullOnInvalid()]);

    $services->alias(ImpersonatorProviderInterface::class, SecurityImpersonatorProvider::class);

    // Listener tags are owned by AuthorableBundle::loadExtension via
    // PrioritizedListenerRegistrar so listener priorities have one
    // source of truth.

    $services->set(AuthorableListener::class)
        ->args([
            service(AuthorProviderInterface::class),
            service(AuthorableMetadataFactory::class),
            service(ChangeSetMatcher::class),
        ]);

    $services->set(ImpersonatorListener::class)
        ->args([
            service(ImpersonatorProviderInterface::class)->nullOnInvalid(),
            service(AuthorableMetadataFactory::class),
        ]);

    $services->set(AuthorableMappingListener::class)
        ->arg('$metadataFactory', service(AuthorableMetadataFactory::class))
        ->arg('$userClass', null);

    $services->set(AuthorableDeletionMarkerProvider::class)
        ->args([
            service(AuthorableMetadataFactory::class),
            service(AuthorProviderInterface::class)->nullOnInvalid(),
        ]);
};
