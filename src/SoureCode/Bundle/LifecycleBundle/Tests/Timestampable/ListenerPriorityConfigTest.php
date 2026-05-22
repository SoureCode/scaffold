<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Timestampable;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\LifecycleBundle\LifecycleBundle;
use SoureCode\Component\Lifecycle\EventListener\TimestampableListener;
use SoureCode\Component\Lifecycle\EventListener\TimestampableMappingListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ListenerPriorityConfigTest extends TestCase
{
    public function testListenerPrioritiesPropagateToDoctrineEventListenerTags(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $bundle = new LifecycleBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), [
            'timestampable' => [
                'listener_priorities' => [
                    'pre_persist' => 250,
                    'on_flush' => -50,
                    'load_class_metadata' => 7,
                ],
            ],
        ]);

        // Run the extension's load() against the builder so loadExtension
        // fires; we don't compile the container because the imported
        // services.php pulls in framework-only deps we don't need to resolve.
        $extension->load($container->getExtensionConfig($extension->getAlias()), $container);

        $tags = $container->getDefinition(TimestampableListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [
                ['event' => 'prePersist', 'priority' => 250],
                ['event' => 'onFlush', 'priority' => -50],
            ],
            $tags,
        );

        $mappingTags = $container->getDefinition(TimestampableMappingListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [
                ['event' => 'loadClassMetadata', 'priority' => 7],
            ],
            $mappingTags,
        );
    }

    public function testListenerPrioritiesDefaultToZero(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $bundle = new LifecycleBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $container->registerExtension($extension);
        $extension->load([[]], $container);

        $tags = $container->getDefinition(TimestampableListener::class)->getTag('doctrine.event_listener');
        self::assertSame(
            [
                ['event' => 'prePersist', 'priority' => 0],
                ['event' => 'onFlush', 'priority' => 0],
            ],
            $tags,
        );
    }
}
