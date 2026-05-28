<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\VersionableBundle\Security\Voter\VersionableVoter;
use SoureCode\Component\Versionable\EventListener\VersionableClassMetadataListener;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Internal\History\EntityProxyFactory;
use SoureCode\Component\Versionable\Internal\History\EntityProxyGenerator;
use SoureCode\Component\Versionable\Internal\History\HistoryClassFactory;
use SoureCode\Component\Versionable\Internal\History\HistoryClassGenerator;
use SoureCode\Component\Versionable\Internal\History\HistoryHydrator;
use SoureCode\Component\Versionable\Internal\PinMaintainer;
use SoureCode\Component\Versionable\Internal\RelationBumpState;
use SoureCode\Component\Versionable\Internal\SnapshotTargetResolver;
use SoureCode\Component\Versionable\Internal\SnapshotWriter;
use SoureCode\Component\Versionable\Internal\VersionIncrementer;
use SoureCode\Component\Versionable\Messenger\PruneVersionableHistoryHandler;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Versioner;
use SoureCode\Component\Versionable\VersionerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(VersionableMetadataFactory::class);

    $services->set(RelationBumpState::class);

    // Doctrine event listener tags for VersionableListener and
    // VersionableSchemaListener live exclusively in VersionableBundle::loadExtension
    // so listener priorities have a single source of truth.

    $services->set(SnapshotTargetResolver::class)
        ->args([
            service(VersionableMetadataFactory::class),
            service(RelationBumpState::class),
        ]);

    $services->set(VersionIncrementer::class)
        ->args([service(VersionableMetadataFactory::class)]);

    $services->set(SnapshotWriter::class)
        ->args([
            service(VersionableMetadataFactory::class),
            service(ClockInterface::class),
            service(LoggerInterface::class)->nullOnInvalid(),
        ]);

    $services->set(PinMaintainer::class)
        ->args([service(VersionableMetadataFactory::class)]);

    $services->set(VersionableListener::class)
        ->args([
            service(SnapshotTargetResolver::class),
            service(VersionIncrementer::class),
            service(SnapshotWriter::class),
            service(PinMaintainer::class),
            service(RelationBumpState::class),
            service(LoggerInterface::class)->nullOnInvalid(),
        ]);

    $services->set(VersionableSchemaListener::class)
        ->args([service(VersionableMetadataFactory::class)]);

    $services->set(HistoryClassGenerator::class)
        ->args([
            service(VersionableMetadataFactory::class),
            service(EntityManagerInterface::class),
        ]);

    $services->set(HistoryClassFactory::class)
        ->args([
            service(HistoryClassGenerator::class),
            '%kernel.cache_dir%/versionable',
        ]);

    $services->set(HistoryHydrator::class)
        ->args([
            service(EntityManagerInterface::class),
            service(VersionableMetadataFactory::class),
            service(HistoryClassFactory::class),
        ]);

    $services->set(EntityProxyGenerator::class)
        ->args([service(VersionableMetadataFactory::class)]);

    $services->set(EntityProxyFactory::class)
        ->args([
            service(EntityProxyGenerator::class),
            '%kernel.cache_dir%/versionable',
        ]);

    $services->set(VersionableClassMetadataListener::class)
        ->args([
            service(EntityProxyFactory::class),
            service(VersionableMetadataFactory::class),
            service(HistoryHydrator::class),
        ]);

    $services->set(Versioner::class)
        ->args([
            service(EntityManagerInterface::class),
            service(VersionableMetadataFactory::class),
            service(LoggerInterface::class)->nullOnInvalid(),
            service(RelationBumpState::class),
            service(HistoryHydrator::class),
        ]);

    $services->alias(VersionerInterface::class, Versioner::class)->public();

    $services->set(PruneVersionableHistoryHandler::class)
        ->args([
            service(VersionerInterface::class),
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        ->tag('messenger.message_handler');

    $services->set(VersionableVoter::class)
        ->tag('security.voter');
};
