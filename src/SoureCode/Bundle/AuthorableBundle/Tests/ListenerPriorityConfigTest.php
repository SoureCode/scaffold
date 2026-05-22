<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\AuthorableBundle\AuthorableBundle;
use SoureCode\Component\Authorable\EventListener\AuthorableListener;
use SoureCode\Component\Authorable\EventListener\AuthorableMappingListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ListenerPriorityConfigTest extends TestCase
{
    public function testListenerPrioritiesPropagateToDoctrineEventListenerTags(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);

        $bundle = new AuthorableBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), [
            'listener_priorities' => [
                'pre_persist' => 250,
                'on_flush' => -50,
                'load_class_metadata' => 7,
            ],
        ]);

        $extension->load($container->getExtensionConfig($extension->getAlias()), $container);

        $tags = $container->getDefinition(AuthorableListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [
                ['event' => 'prePersist', 'priority' => 250],
                ['event' => 'onFlush', 'priority' => -50],
            ],
            $tags,
        );

        $mappingTags = $container->getDefinition(AuthorableMappingListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [['event' => 'loadClassMetadata', 'priority' => 7]],
            $mappingTags,
        );
    }

    public function testListenerPrioritiesDefaultToZero(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);

        $bundle = new AuthorableBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $container->registerExtension($extension);
        $extension->load([[]], $container);

        $tags = $container->getDefinition(AuthorableListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [
                ['event' => 'prePersist', 'priority' => 0],
                ['event' => 'onFlush', 'priority' => 0],
            ],
            $tags,
        );
    }
}
