<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\VersionableBundle\VersionableBundle;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ListenerPriorityConfigTest extends TestCase
{
    public function testListenerPrioritiesPropagateToDoctrineEventListenerTags(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);

        $bundle = new VersionableBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), [
            'listener_priorities' => [
                'on_flush' => 33,
                'post_flush' => -33,
                'post_generate_schema' => 11,
            ],
        ]);

        $extension->load($container->getExtensionConfig($extension->getAlias()), $container);

        $listenerTags = $container->getDefinition(VersionableListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [
                ['event' => 'onFlush', 'priority' => 33],
                ['event' => 'postFlush', 'priority' => -33],
            ],
            $listenerTags,
        );

        $schemaTags = $container->getDefinition(VersionableSchemaListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [['event' => 'postGenerateSchema', 'priority' => 11]],
            $schemaTags,
        );
    }

    public function testDefaultsPlaceVersionableListenerLateInTheFlushCycle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);

        $bundle = new VersionableBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $container->registerExtension($extension);
        $extension->load([[]], $container);

        $tags = $container->getDefinition(VersionableListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [
                ['event' => 'onFlush', 'priority' => -100],
                ['event' => 'postFlush', 'priority' => -100],
            ],
            $tags,
            'snapshots are taken AFTER stamping listeners',
        );
    }
}
