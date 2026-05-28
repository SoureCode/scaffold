<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle;

use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\ToolEvents;
use SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection\ListenerPrioritiesConfigBuilder;
use SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection\PrioritizedListenerRegistrar;
use SoureCode\Component\Versionable\EventListener\VersionableClassMetadataListener;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class VersionableBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->append(ListenerPrioritiesConfigBuilder::build([
                    'on_flush' => -100,
                    'post_flush' => -100,
                    'post_generate_schema' => 0,
                    'load_class_metadata' => 0,
                ]))
            ->end();
    }

    /**
     * @param array{listener_priorities: array{on_flush: int, post_flush: int, post_generate_schema: int, load_class_metadata: int}} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');

        PrioritizedListenerRegistrar::register($builder, VersionableListener::class, [
            Events::onFlush => $config['listener_priorities']['on_flush'],
            Events::postFlush => $config['listener_priorities']['post_flush'],
        ]);

        PrioritizedListenerRegistrar::register($builder, VersionableSchemaListener::class, [
            ToolEvents::postGenerateSchema => $config['listener_priorities']['post_generate_schema'],
        ]);

        PrioritizedListenerRegistrar::register($builder, VersionableClassMetadataListener::class, [
            Events::loadClassMetadata => $config['listener_priorities']['load_class_metadata'],
        ]);
    }
}
