<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Removable\Remover;
use SoureCode\Component\Removable\RemoverInterface;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Remover::class)
        ->args([
            service(EntityManagerInterface::class),
            service(ClockInterface::class),
            service(TimestampableMetadataFactory::class),
            service(AuthorableMetadataFactory::class),
            service(AuthorProviderInterface::class)->nullOnInvalid(),
        ]);

    $services->alias(RemoverInterface::class, Remover::class)->public();
};
