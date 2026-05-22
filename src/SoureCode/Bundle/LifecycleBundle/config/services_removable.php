<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Component\Lifecycle\DeletionMarkerProviderInterface;
use SoureCode\Component\Lifecycle\Remover;
use SoureCode\Component\Lifecycle\RemoverInterface;
use SoureCode\Component\Lifecycle\Metadata\TimestampableMetadataFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->instanceof(DeletionMarkerProviderInterface::class)
        ->tag('sourecode.removable.deletion_marker_provider');

    $services->set(Remover::class)
        ->args([
            service(EntityManagerInterface::class),
            service(ClockInterface::class),
            service(TimestampableMetadataFactory::class),
            tagged_iterator('sourecode.removable.deletion_marker_provider'),
            service(LoggerInterface::class)->nullOnInvalid(),
        ]);

    $services->alias(RemoverInterface::class, Remover::class)->public();
};
