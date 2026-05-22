<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle;

use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\ToolEvents;
use SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection\PrioritizedListenerRegistrar;
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
                ->arrayNode('listener_priorities')
                    ->addDefaultsIfNotSet()
                    ->info('Doctrine event listener priorities. Higher numbers run first. Versionable snapshots the entity in postFlush; the onFlush phase only collects targets.')
                    ->children()
                        ->integerNode('on_flush')->defaultValue(-100)->end()
                        ->integerNode('post_flush')->defaultValue(-100)->end()
                        ->integerNode('post_generate_schema')->defaultValue(0)->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{listener_priorities: array{on_flush: int, post_flush: int, post_generate_schema: int}} $config
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
    }
}
